#!/usr/bin/env python3
"""
FactScan AI - Multi-Feature Machine Learning Model Trainer
Trains a combined TF-IDF + Linguistic Feature Extraction Pipeline on news verification datasets.
"""

import os
import sys
import argparse
import joblib
import pandas as pd
import numpy as np

from sklearn.model_selection import train_test_split
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.pipeline import FeatureUnion, Pipeline
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import classification_report, accuracy_score, confusion_matrix

# Import custom engineered linguistic feature extractor
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
if BASE_DIR not in sys.path:
    sys.path.insert(0, BASE_DIR)

from features import LinguisticFeatureExtractor

DATA_DIR = os.path.join(BASE_DIR, "data")
MODEL_DIR = os.path.join(BASE_DIR, "model")

os.makedirs(MODEL_DIR, exist_ok=True)

def parse_args():
    parser = argparse.ArgumentParser(description="Train FactScan AI multi-feature model")
    parser.add_argument(
        "--data",
        type=str,
        default=os.path.join(DATA_DIR, "large_news_dataset.csv"),
        help="Path to training CSV dataset"
    )
    parser.add_argument(
        "--output_dir",
        type=str,
        default=MODEL_DIR,
        help="Directory to save model pipeline"
    )
    return parser.parse_args()

def load_dataset(file_path):
    print(f"[*] Loading dataset from: {file_path}")
    if not os.path.exists(file_path):
        raise FileNotFoundError(f"Dataset file not found: {file_path}")

    df = pd.read_csv(file_path)

    # Detect text columns
    text_col = None
    for candidate in ["text", "claim", "statement", "headline", "content"]:
        if candidate in df.columns:
            text_col = candidate
            break

    # If title is also present, combine title + text
    if "title" in df.columns and text_col and text_col != "title":
        df["combined_text"] = df["title"].fillna("") + " " + df[text_col].fillna("")
        text_col = "combined_text"
    elif not text_col and "title" in df.columns:
        text_col = "title"

    if not text_col:
        raise ValueError(f"Could not find a valid text column in dataset. Available columns: {list(df.columns)}")

    # Detect label column
    label_col = None
    for candidate in ["label", "is_real", "verdict", "target", "class"]:
        if candidate in df.columns:
            label_col = candidate
            break

    if not label_col:
        raise ValueError(f"Could not find a valid label column in dataset. Available columns: {list(df.columns)}")

    # Standardize label to 1 (Real) and 0 (Fake)
    df = df.dropna(subset=[text_col, label_col])
    if df[label_col].dtype == object:
        df[label_col] = df[label_col].astype(str).str.strip().str.upper()
        df[label_col] = df[label_col].map({
            "TRUE": 1, "REAL": 1, "1": 1, "MOSTLY-TRUE": 1, "HALF-TRUE": 1,
            "FALSE": 0, "FAKE": 0, "0": 0, "BARELY-TRUE": 0, "PANTS-FIRE": 0
        }).fillna(0).astype(int)
    else:
        df[label_col] = df[label_col].astype(int)

    X = df[text_col].astype(str)
    y = df[label_col].values

    print(f"[+] Loaded {len(df)} samples (Real: {sum(y == 1)}, Fake: {sum(y == 0)})")
    return X, y

def train(data_path, output_dir):
    X, y = load_dataset(data_path)

    test_size = 0.2 if len(X) >= 50 else 0.25
    if len(X) < 10:
        X_train, X_test, y_train, y_test = X, X, y, y
    else:
        X_train, X_test, y_train, y_test = train_test_split(
            X, y, test_size=test_size, random_state=42, stratify=y
        )

    print("[*] Building Multi-Feature Pipeline:")
    print("    - Branch 1: TF-IDF N-Gram Vectorizer (1-2 ngrams, sublinear scaling)")
    print("    - Branch 2: Custom Linguistic Feature Extractor (Sensationalism, Urgency, Attribution, Punctuation)")

    feature_union = FeatureUnion([
        ('tfidf', TfidfVectorizer(
            max_features=25000,
            ngram_range=(1, 2),
            sublinear_tf=True,
            stop_words="english"
        )),
        ('linguistic', LinguisticFeatureExtractor())
    ])

    classifier = LogisticRegression(
        C=2.0,
        max_iter=1500,
        class_weight="balanced",
        random_state=42
    )

    pipeline = Pipeline([
        ('features', feature_union),
        ('clf', classifier)
    ])

    print("[*] Training end-to-end model pipeline...")
    pipeline.fit(X_train, y_train)

    # Evaluation
    preds = pipeline.predict(X_test)
    acc = accuracy_score(y_test, preds)
    print(f"\n[+] Training Complete!")
    print(f"[+] Test Accuracy: {acc * 100:.2f}%")
    print("\nClassification Report:")
    print(classification_report(y_test, preds, target_names=["Fake (0)", "Real (1)"], zero_division=0))

    # Save artifacts
    pipeline_path = os.path.join(output_dir, "model.joblib")
    joblib.dump(pipeline, pipeline_path)
    print(f"[+] Saved trained multi-feature pipeline to: {pipeline_path}")

if __name__ == "__main__":
    args = parse_args()
    train(args.data, args.output_dir)
