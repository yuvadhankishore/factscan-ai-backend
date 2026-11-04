#!/usr/bin/env python3
"""
FactScan AI - Advanced Multi-Feature Prediction CLI
Inference engine utilizing Soft-Voting Ensemble (Calibrated LinearSVC + Logistic Regression + ComplementNB)
and 12-dimensional linguistic feature breakdown.
"""

import os
import sys
import json
import argparse

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
if BASE_DIR not in sys.path:
    sys.path.insert(0, BASE_DIR)

from features import LinguisticFeatureExtractor

MODEL_DIR = os.path.join(BASE_DIR, "model")
MODEL_PATH = os.path.join(MODEL_DIR, "model.joblib")

def extract_rule_signals(text, feats):
    signals = []

    if feats.get("sensationalismIndex", 0) >= 30:
        signals.append("High sensationalism index detected in vocabulary")

    if feats.get("urgencyPanicLevel", 0) >= 25:
        signals.append("Urgent social forwarding call-to-action detected")

    if feats.get("fearMongeringLevel", 0) >= 25:
        signals.append("Alarmist fear-inducing phraseology identified")

    if feats.get("definitiveCertaintyBias", 0) >= 20:
        signals.append("Unsubstantiated absolute certainty claims detected")

    if feats.get("excessivePunctuation", 0) >= 20:
        signals.append("Elevated punctuation frequency typical of viral alerts")

    if feats.get("institutionalAttribution", 0) >= 30:
        signals.append("Verified institutional and journalistic attribution terms identified")

    if len(text.split()) < 4:
        signals.append("Very brief input with limited contextual facts")

    return signals

def predict(text):
    if not text or not text.strip():
        return {
            "success": False,
            "message": "Empty text provided"
        }

    cleaned_text = text.strip()
    headline = cleaned_text[:75] + ("..." if len(cleaned_text) > 75 else "")

    feats = LinguisticFeatureExtractor.get_feature_dict(cleaned_text)
    warning_signals = extract_rule_signals(cleaned_text, feats)

    has_model = os.path.exists(MODEL_PATH)

    if has_model:
        try:
            import joblib
            pipeline = joblib.load(MODEL_PATH)

            proba = pipeline.predict_proba([cleaned_text])[0]
            prob_fake = float(proba[0])
            prob_real = float(proba[1])

            is_real = prob_real >= 0.50
            confidence = int(round(max(prob_real, prob_fake) * 100))

            if not is_real and not warning_signals:
                warning_signals.append("Linguistic patterns align with unsubstantiated online claims")
            elif is_real and not warning_signals:
                warning_signals.append("Phrasing matches journalistic factual reporting registries")

            feature_indicators = []
            if feats.get("sensationalismIndex", 0) > 25:
                feature_indicators.append(f"Sensationalism score: {feats['sensationalismIndex']}% (Elevated)")
            if feats.get("urgencyPanicLevel", 0) > 20:
                feature_indicators.append(f"Viral urgency level: {feats['urgencyPanicLevel']}% (High)")
            if feats.get("fearMongeringLevel", 0) > 20:
                feature_indicators.append(f"Alarmist fear level: {feats['fearMongeringLevel']}%")
            if feats.get("institutionalAttribution", 0) > 25:
                feature_indicators.append(f"Institutional credibility: {feats['institutionalAttribution']}% (Corroborated)")
            if feats.get("factualNumericalDensity", 0) > 15:
                feature_indicators.append(f"Numerical evidence density: {feats['factualNumericalDensity']}%")

            return {
                "success": True,
                "isReal": is_real,
                "confidence": confidence,
                "probabilities": {
                    "real": round(prob_real, 4),
                    "fake": round(prob_fake, 4)
                },
                "features": feats,
                "topFeatureIndicators": feature_indicators,
                "warningSignals": warning_signals,
                "headline": headline,
                "engine": "scikit-learn_advanced_soft_voting_ensemble"
            }
        except Exception as e:
            pass

    # Heuristic Rule Fallback
    is_real = feats.get("institutionalAttribution", 0) >= feats.get("sensationalismIndex", 0)
    return {
        "success": True,
        "isReal": is_real,
        "confidence": 75,
        "probabilities": {
            "real": 0.75 if is_real else 0.25,
            "fake": 0.25 if is_real else 0.75
        },
        "features": feats,
        "warningSignals": warning_signals or ["Evaluated via linguistic feature rule analysis"],
        "headline": headline,
        "engine": "heuristic_feature_fallback"
    }

def main():
    parser = argparse.ArgumentParser(description="Predict news claim authenticity using advanced AI")
    parser.add_argument("--text", type=str, default=None, help="Text claim to evaluate")
    args = parser.parse_args()

    input_text = args.text
    if not input_text:
        if not sys.stdin.isatty():
            input_text = sys.stdin.read()

    if not input_text:
        print(json.dumps({"success": False, "message": "No input text provided"}))
        sys.exit(1)

    result = predict(input_text)
    print(json.dumps(result))

if __name__ == "__main__":
    main()
