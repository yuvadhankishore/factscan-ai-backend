#!/usr/bin/env python3
"""
FactScan AI - Multi-Domain Dataset Compiler
Merges Geopolitics, Medicine, Astronomy, Finance, Technology, and Batch 2 claims
into unified, deduplicated large_news_dataset.csv and sample_dataset.csv.
"""

import os
import sys
import csv
import pandas as pd

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
if BASE_DIR not in sys.path:
    sys.path.insert(0, BASE_DIR)

from data_geopolitics import GEOPOLITICAL_CORPUS
from data_medicine import MEDICINE_CORPUS
from data_astronomy import ASTRONOMY_CORPUS
from data_finance import FINANCE_CORPUS
from data_tech import TECH_CORPUS

DATA_DIR = os.path.join(BASE_DIR, "data")

def compile_dataset():
    all_new_records = []
    
    # Add domain corpora
    for corpus in [GEOPOLITICAL_CORPUS, MEDICINE_CORPUS, ASTRONOMY_CORPUS, FINANCE_CORPUS, TECH_CORPUS]:
        for title, text, label in corpus:
            all_new_records.append({
                "title": title.strip(),
                "text": text.strip(),
                "label": int(label)
            })

    # Read batch 2 samples if present
    batch2_file = os.path.join(r"C:\xampp\htdocs", "expand_advance_batch2.py")
    if os.path.exists(batch2_file):
        try:
            import importlib.util
            spec = importlib.util.spec_from_file_location("batch2_mod", batch2_file)
            batch2_mod = importlib.util.module_from_spec(spec)
            spec.loader.exec_module(batch2_mod)
            if hasattr(batch2_mod, "BATCH_2_SAMPLES"):
                for item in batch2_mod.BATCH_2_SAMPLES:
                    all_new_records.append({
                        "title": item["title"].strip(),
                        "text": item["text"].strip(),
                        "label": int(item["label"])
                    })
        except Exception as e:
            print(f"[!] Warning reading batch2: {e}")

    print(f"[*] Aggregated {len(all_new_records)} modern domain-specific verification claims")

    # Update datasets
    for filename in ["large_news_dataset.csv", "sample_dataset.csv"]:
        target_path = os.path.join(DATA_DIR, filename)
        if not os.path.exists(target_path):
            continue

        existing_df = pd.read_csv(target_path)
        existing_titles = set(existing_df["title"].astype(str).str.lower().str.strip())

        new_rows = []
        for r in all_new_records:
            if r["title"].lower().strip() not in existing_titles:
                new_rows.append(r)
                existing_titles.add(r["title"].lower().strip())

        if new_rows:
            new_df = pd.DataFrame(new_rows)
            combined_df = pd.concat([existing_df, new_df], ignore_index=True)
            combined_df.to_csv(target_path, index=False, encoding="utf-8")
            print(f"[+] Successfully integrated {len(new_rows)} new unique claims into {filename} (Total: {len(combined_df)})")
        else:
            print(f"[*] {filename} is already up to date (Total: {len(existing_df)})")

if __name__ == "__main__":
    compile_dataset()
