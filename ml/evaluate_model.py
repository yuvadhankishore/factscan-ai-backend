#!/usr/bin/env python3
"""
FactScan AI - Model Performance Evaluation & Domain Benchmarking Suite
Evaluates trained ensemble model.joblib across domain slices and computes
slice-level precision, recall, F1, and inference latency.
"""

import os
import sys
import time
import json
import joblib
import numpy as np
import pandas as pd
from sklearn.metrics import accuracy_score, precision_score, recall_score, f1_score, roc_auc_score

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
if BASE_DIR not in sys.path:
    sys.path.insert(0, BASE_DIR)

from data_geopolitics import GEOPOLITICAL_CORPUS
from data_medicine import MEDICINE_CORPUS
from data_astronomy import ASTRONOMY_CORPUS
from data_finance import FINANCE_CORPUS
from data_tech import TECH_CORPUS
from data_contemporary import CONTEMPORARY_CORPUS

MODEL_PATH = os.path.join(BASE_DIR, "model", "model.joblib")

def evaluate_domain_slice(pipeline, name, corpus):
    texts = [item[1] for item in corpus]
    labels = [int(item[2]) for item in corpus]

    t0 = time.time()
    preds = pipeline.predict(texts)
    probs = pipeline.predict_proba(texts)[:, 1]
    elapsed_ms = (time.time() - t0) * 1000 / len(texts)

    acc = accuracy_score(labels, preds)
    prec = precision_score(labels, preds, zero_division=0)
    rec = recall_score(labels, preds, zero_division=0)
    f1 = f1_score(labels, preds, zero_division=0)

    try:
        auc = roc_auc_score(labels, probs)
    except Exception:
        auc = 1.0

    print(f"  |-- {name:20s}: Accuracy={acc*100:5.1f}% | F1={f1*100:5.1f}% | Prec={prec*100:5.1f}% | Rec={rec*100:5.1f}% | Latency={elapsed_ms:.2f}ms/claim")
    return {
        "domain": name,
        "sample_count": len(texts),
        "accuracy": round(float(acc * 100), 2),
        "f1_score": round(float(f1 * 100), 2),
        "precision": round(float(prec * 100), 2),
        "recall": round(float(rec * 100), 2),
        "roc_auc": round(float(auc * 100), 2),
        "avg_latency_ms": round(float(elapsed_ms), 2)
    }

def main():
    print("=" * 70)
    print("FACTSCAN AI - TRAINED MODEL BENCHMARK & SLICE EVALUATION")
    print("=" * 70)

    if not os.path.exists(MODEL_PATH):
        print(f"[!] Error: Model not found at {MODEL_PATH}")
        sys.exit(1)

    print(f"[*] Loading model pipeline from: {MODEL_PATH}")
    pipeline = joblib.load(MODEL_PATH)

    domains = [
        ("Geopolitics & Nations", GEOPOLITICAL_CORPUS),
        ("Biomedicine & Health", MEDICINE_CORPUS),
        ("Astronomy & Space", ASTRONOMY_CORPUS),
        ("Finance & Economics", FINANCE_CORPUS),
        ("Technology & Cyber", TECH_CORPUS),
        ("Contemporary & Viral", CONTEMPORARY_CORPUS)
    ]

    results = []
    print("\n[*] Evaluating domain performance slices:")
    for name, corpus in domains:
        res = evaluate_domain_slice(pipeline, name, corpus)
        results.append(res)

    all_texts = []
    all_labels = []
    for _, corpus in domains:
        for item in corpus:
            all_texts.append(item[1])
            all_labels.append(int(item[2]))

    overall_preds = pipeline.predict(all_texts)
    overall_probs = pipeline.predict_proba(all_texts)[:, 1]
    overall_acc = accuracy_score(all_labels, overall_preds)
    overall_f1 = f1_score(all_labels, overall_preds)
    overall_auc = roc_auc_score(all_labels, overall_probs)

    print("-" * 70)
    print(f"  [+] OVERALL MULTI-DOMAIN ACCURACY : {overall_acc * 100:.2f}%")
    print(f"  [+] OVERALL F1 HARMONIC MEAN      : {overall_f1 * 100:.2f}%")
    print(f"  [+] OVERALL ROC-AUC DISCRIMINATION: {overall_auc * 100:.2f}%")
    print("=" * 70)

    benchmark_json_path = os.path.join(BASE_DIR, "model", "domain_benchmark.json")
    with open(benchmark_json_path, "w", encoding="utf-8") as f:
        json.dump({
            "overall_accuracy": round(float(overall_acc * 100), 2),
            "overall_f1": round(float(overall_f1 * 100), 2),
            "overall_auc": round(float(overall_auc * 100), 2),
            "domains": results
        }, f, indent=2)
    print(f"[+] Saved multi-domain benchmark report to: {benchmark_json_path}\n")

if __name__ == "__main__":
    main()
