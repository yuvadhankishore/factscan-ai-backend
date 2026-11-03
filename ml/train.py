#!/usr/bin/env python3
"""
FactScan AI - Advanced Multi-Branch Ensemble Model Trainer
Combines Word N-Grams, Character N-Grams, and Deep Linguistic Feature Extraction
with an Ensemble of Calibrated LinearSVC, Logistic Regression, and Complement Naive Bayes.
"""

import os
import sys
import argparse
import joblib
import pandas as pd
import numpy as np

from sklearn.model_selection import train_test_split, StratifiedKFold, cross_val_score
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.pipeline import FeatureUnion, Pipeline
from sklearn.linear_model import LogisticRegression
from sklearn.svm import LinearSVC
from sklearn.calibration import CalibratedClassifierCV
from sklearn.naive_bayes import ComplementNB
from sklearn.ensemble import VotingClassifier
from sklearn.metrics import (
    classification_report,
    accuracy_score,
    roc_auc_score,
    confusion_matrix,
    f1_score,
    precision_score,
    recall_score
)

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
if BASE_DIR not in sys.path:
    sys.path.insert(0, BASE_DIR)

from features import LinguisticFeatureExtractor

DATA_DIR = os.path.join(BASE_DIR, "data")
MODEL_DIR = os.path.join(BASE_DIR, "model")

os.makedirs(MODEL_DIR, exist_ok=True)

def parse_args():
    parser = argparse.ArgumentParser(description="Train FactScan AI Advanced Model")
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
    print(f"[*] Loading training corpus from: {file_path}")
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
        raise ValueError(f"Could not find a valid text column in dataset. Available: {list(df.columns)}")

    # Detect label column
    label_col = None
    for candidate in ["label", "is_real", "verdict", "target", "class"]:
        if candidate in df.columns:
            label_col = candidate
            break

    if not label_col:
        raise ValueError(f"Could not find a valid label column. Available: {list(df.columns)}")

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

    print(f"[+] Loaded {len(df)} authentic & debunked samples (Real: {sum(y == 1)}, Fake: {sum(y == 0)})")
    return X, y

def train(data_path, output_dir):
    X, y = load_dataset(data_path)

    test_size = 0.2 if len(X) >= 50 else 0.25
    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=test_size, random_state=42, stratify=y
    )

    print("\n[*] Constructing Multi-Branch Deep Feature Engineering Pipeline:")
    print("    |-- Branch 1: Sublinear Word N-Grams (1-3 ngrams, max 30,000 features)")
    print("    |-- Branch 2: Character N-Grams (3-5 char ngrams, max 15,000 features for typos & morphology)")
    print("    \\-- Branch 3: 12-Dimensional Deep Linguistic & Stylometric Extractor")

    feature_union = FeatureUnion([
        ('word_tfidf', TfidfVectorizer(
            ngram_range=(1, 3),
            max_features=30000,
            sublinear_tf=True,
            min_df=2,
            stop_words="english"
        )),
        ('char_tfidf', TfidfVectorizer(
            analyzer='char_wb',
            ngram_range=(3, 5),
            max_features=15000,
            sublinear_tf=True,
            min_df=2
        )),
        ('linguistic', LinguisticFeatureExtractor())
    ])

    print("\n[*] Constructing Advanced Soft-Voting Ensemble Classifier:")
    print("    |-- Model A: Calibrated Linear Support Vector Classifier (LinearSVC with Platt Scaling, weight=2)")
    print("    |-- Model B: Balanced L2-Regularized Logistic Regression (C=2.5, weight=2)")
    print("    \\-- Model C: Complement Naive Bayes (alpha=0.4, weight=1)")

    model_svc = CalibratedClassifierCV(
        estimator=LinearSVC(C=1.2, max_iter=2500, random_state=42),
        cv=3
    )
    model_lr = LogisticRegression(
        C=2.5,
        max_iter=2000,
        class_weight="balanced",
        random_state=42
    )
    model_cnb = ComplementNB(alpha=0.4, norm=True)

    ensemble = VotingClassifier(
        estimators=[
            ('svc', model_svc),
            ('lr', model_lr),
            ('cnb', model_cnb)
        ],
        voting='soft',
        weights=[2, 2, 1]
    )

    pipeline = Pipeline([
        ('features', feature_union),
        ('clf', ensemble)
    ])

    # Perform 5-Fold Stratified Cross Validation on Training Set
    print("\n[*] Running 5-Fold Stratified Cross-Validation on Training Corpus...")
    skf = StratifiedKFold(n_splits=5, shuffle=True, random_state=42)
    cv_scores = cross_val_score(pipeline, X_train, y_train, cv=skf, scoring='accuracy')
    print(f"[+] 5-Fold CV Accuracy: {cv_scores.mean() * 100:.2f}% (+/- {cv_scores.std() * 100:.2f}%)")

    # Fit Full Pipeline on Training Data
    print("[*] Training final ensemble model pipeline...")
    pipeline.fit(X_train, y_train)

    # Evaluation on Held-Out Test Set
    test_preds = pipeline.predict(X_test)
    test_proba = pipeline.predict_proba(X_test)[:, 1]

    acc = accuracy_score(y_test, test_preds)
    f1 = f1_score(y_test, test_preds)
    precision = precision_score(y_test, test_preds)
    recall = recall_score(y_test, test_preds)
    roc_auc = roc_auc_score(y_test, test_proba)
    cm = confusion_matrix(y_test, test_preds)

    print("\n" + "=" * 60)
    print("ADVANCED PYTHON AI EVALUATION BENCHMARK")
    print("=" * 60)
    print(f"Overall Test Accuracy:       {acc * 100:.2f}%")
    print(f"ROC-AUC Score:               {roc_auc * 100:.2f}%")
    print(f"F1-Score (Harmonic Mean):    {f1 * 100:.2f}%")
    print(f"Precision:                   {precision * 100:.2f}%")
    print(f"Recall (Sensitivity):        {recall * 100:.2f}%")
    print("\nConfusion Matrix:")
    print(f"  True Negatives (Fake identified as Fake): {cm[0][0]}")
    print(f"  False Positives (Fake flagged as Real):   {cm[0][1]}")
    print(f"  False Negatives (Real flagged as Fake):   {cm[1][0]}")
    print(f"  True Positives (Real identified as Real): {cm[1][1]}")
    print("\nDetailed Classification Breakdown:")
    print(classification_report(y_test, test_preds, target_names=["Fake News (0)", "Real News (1)"], zero_division=0))
    print("=" * 60)

    # Serialize Artifact
    pipeline_path = os.path.join(output_dir, "model.joblib")
    joblib.dump(pipeline, pipeline_path, compress=3)
    print(f"\n[+] Successfully saved compressed advanced AI model pipeline to:")
    print(f"    --> {pipeline_path} ({os.path.getsize(pipeline_path) / 1024:.1f} KB)")

if __name__ == "__main__":
    args = parse_args()
    train(args.data, args.output_dir)
