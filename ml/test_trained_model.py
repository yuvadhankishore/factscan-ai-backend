#!/usr/bin/env python3
"""
FactScan AI - Retrained Model Verification & Unit Test Suite
Validates the serialized ensemble pipeline (model.joblib) across
contemporary real news stories, viral hoaxes, and latency bounds.
"""

import os
import sys
import time
import unittest
import joblib

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
if BASE_DIR not in sys.path:
    sys.path.insert(0, BASE_DIR)

from predict import predict
from features import LinguisticFeatureExtractor

MODEL_PATH = os.path.join(BASE_DIR, "model", "model.joblib")

class TestTrainedModel(unittest.TestCase):

    @classmethod
    def setUpClass(cls):
        cls.has_model = os.path.exists(MODEL_PATH)
        if cls.has_model:
            cls.pipeline = joblib.load(MODEL_PATH)

    def test_model_artifact_exists(self):
        self.assertTrue(self.has_model, f"model.joblib should exist at {MODEL_PATH}")
        size_kb = os.path.getsize(MODEL_PATH) / 1024
        self.assertGreater(size_kb, 500, "Model artifact should be at least 500 KB")

    def test_raw_pipeline_inference(self):
        real_text = "The Reserve Bank of India decided to maintain the policy repo rate unchanged at 6.5 percent."
        fake_text = "Secret emergency trick: forward this text to 20 people and get free 50000 cash from the government!"

        preds = self.pipeline.predict([real_text, fake_text])
        probas = self.pipeline.predict_proba([real_text, fake_text])

        self.assertEqual(preds[0], 1, "Authentic financial news should be predicted as Real (1)")
        self.assertEqual(preds[1], 0, "Spam phishing message should be predicted as Fake (0)")
        self.assertGreater(probas[0][1], 0.70, "Real text should have real probability > 0.70")
        self.assertGreater(probas[1][0], 0.70, "Fake text should have fake probability > 0.70")

    def test_contemporary_real_claims(self):
        claims = [
            "ISRO and NASA completed testing for the NISAR environmental radar satellite mission.",
            "The World Health Organization confirmed an expanding malaria vaccination program in Africa.",
            "European Central Bank policymakers left key interest rates steady following lower inflation numbers."
        ]
        for c in claims:
            res = predict(c, check_cross_sources=False)
            self.assertTrue(res.get("success"), f"Prediction failed for: {c}")
            self.assertEqual(res.get("verdict"), "REAL", f"Expected REAL for: {c}")
            self.assertTrue(res.get("isReal"), f"Expected isReal=True for: {c}")
            self.assertGreaterEqual(res.get("confidence", 0), 80)

    def test_contemporary_fake_claims(self):
        claims = [
            "Forward this message immediately: government will shut down the internet and ATMs nationwide tomorrow!",
            "Doctors hide this miracle frequency that cures brain cancer in 48 hours without surgery.",
            "NASA leaked classified video showing a tropical civilization hidden behind the Antarctic ice wall."
        ]
        for c in claims:
            res = predict(c, check_cross_sources=False)
            self.assertTrue(res.get("success"), f"Prediction failed for: {c}")
            self.assertEqual(res.get("verdict"), "FAKE", f"Expected FAKE for: {c}")
            self.assertFalse(res.get("isReal"), f"Expected isReal=False for: {c}")
            self.assertGreaterEqual(res.get("confidence", 0), 80)

    def test_feature_extractor_rich_indicators(self):
        sample = "WHO announced preliminary clinical trial results for pediatric immunology treatments today."
        feats = LinguisticFeatureExtractor.get_feature_dict(sample)
        self.assertIn("epistemicModality", feats)
        self.assertIn("viralCtaDensity", feats)
        self.assertGreater(feats["epistemicModality"], 0)
        self.assertEqual(feats["viralCtaDensity"], 0)

    def test_inference_latency(self):
        sample = "A team of researchers at Oxford University published new findings in Nature Medicine."
        t0 = time.time()
        for _ in range(5):
            predict(sample, check_cross_sources=False)
        avg_ms = ((time.time() - t0) / 5) * 1000
        self.assertLess(avg_ms, 100, f"Average inference should be under 100ms, got {avg_ms:.2f}ms")

if __name__ == "__main__":
    unittest.main()
