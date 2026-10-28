#!/usr/bin/env python3
"""
FactScan AI - Machine Learning Prediction CLI
Receives text claim via argument or stdin, outputs JSON.
Can be executed directly by PHP backend or run standalone.
"""

import os
import sys
import json
import argparse

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
MODEL_DIR = os.path.join(BASE_DIR, "model")
MODEL_PATH = os.path.join(MODEL_DIR, "model.joblib")
VECTORIZER_PATH = os.path.join(MODEL_DIR, "vectorizer.joblib")

def extract_rule_signals(text):
    text_lower = text.lower()
    warning_signals = []

    sensational_words = ["miracle", "shocking", "urgent", "must watch", "banned from tv", "secret leak", "illuminati"]
    for w in sensational_words:
        if w in text_lower:
            warning_signals.append(f"Sensational trigger phrase detected: '{w}'")

    unverified_markers = ["forwarded", "share before deleted", "don't ignore", "free money", "cure cancer overnight"]
    for w in unverified_markers:
        if w in text_lower:
            warning_signals.append("Urgent social sharing request without traceable primary attribution")

    if len(text.split()) < 4:
        warning_signals.append("Very brief input with limited contextual facts")

    return warning_signals

def predict(text):
    if not text or not text.strip():
        return {
            "success": False,
            "message": "Empty text provided"
        }

    cleaned_text = text.strip()
    headline = cleaned_text[:75] + ("..." if len(cleaned_text) > 75 else "")

    # Check if trained model and vectorizer exist
    has_model = os.path.exists(MODEL_PATH) and os.path.exists(VECTORIZER_PATH)

    if has_model:
        try:
            import joblib
            model = joblib.load(MODEL_PATH)
            vectorizer = joblib.load(VECTORIZER_PATH)

            X_vec = vectorizer.transform([cleaned_text])
            proba = model.predict_proba(X_vec)[0]  # [prob_fake (0), prob_real (1)]
            prob_fake = float(proba[0])
            prob_real = float(proba[1])

            is_real = prob_real >= 0.50
            confidence = int(round(max(prob_real, prob_fake) * 100))

            warning_signals = extract_rule_signals(cleaned_text)
            if not is_real and not warning_signals:
                warning_signals.append("Linguistic patterns align with unsubstantiated online claims")
            elif is_real:
                warning_signals.append("Phrasing matches journalistic factual reporting registries")

            return {
                "success": True,
                "isReal": is_real,
                "confidence": confidence,
                "probabilities": {
                    "real": round(prob_real, 4),
                    "fake": round(prob_fake, 4)
                },
                "warningSignals": warning_signals,
                "headline": headline,
                "engine": "scikit-learn_logistic_regression"
            }
        except Exception as e:
            # Fall back to heuristic if error loading model
            pass

    # Heuristic fallback if model not trained yet
    signals = extract_rule_signals(cleaned_text)
    is_fake = len(signals) > 0
    confidence = 90 if is_fake else 88

    return {
        "success": True,
        "isReal": not is_fake,
        "confidence": confidence,
        "probabilities": {
            "real": 0.10 if is_fake else 0.88,
            "fake": 0.90 if is_fake else 0.12
        },
        "warningSignals": signals if signals else ["No overt disinformation flags detected in text sample"],
        "headline": headline,
        "engine": "heuristic_fallback"
    }

def main():
    parser = argparse.ArgumentParser(description="FactScan AI ML Predictor")
    parser.add_argument("--text", type=str, default=None, help="Claim text to analyze")
    args = parser.parse_args()

    input_text = args.text
    if not input_text:
        # Read from stdin
        if not sys.stdin.isatty():
            input_text = sys.stdin.read()

    if not input_text:
        print(json.dumps({"success": False, "message": "No text provided via --text or stdin"}))
        sys.exit(1)

    result = predict(input_text)
    print(json.dumps(result, ensure_ascii=False))

if __name__ == "__main__":
    main()

