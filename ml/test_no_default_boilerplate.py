#!/usr/bin/env python3
"""
FactScan AI - Automated Regression Test: Elimination of Default Boilerplate Summaries
Ensures that all content formats (short claims, unlisted claims, documents, and URLs)
receive dynamic, claim-specific rationales and never return static fallback messages.
"""

import os
import sys
import unittest

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
if BASE_DIR not in sys.path:
    sys.path.insert(0, BASE_DIR)

from predict import predict

FORBIDDEN_BOILERPLATES = [
    "conforms to authentic journalistic vocabulary and corroborated digital records",
    "typical of online viral rumors.",
    "FactScan AI linguistic analysis determined that this content conforms to authentic journalistic vocabulary",
    "Verified analysis result generated from authentic user content."
]

class TestNoDefaultBoilerplate(unittest.TestCase):

    def assert_no_boilerplate(self, result, claim_label):
        self.assertTrue(result.get("success"), f"Prediction failed for: {claim_label}")
        summary = result.get("summary", "")
        self.assertTrue(len(summary) > 20, f"Summary is empty or too short for: {claim_label}")
        for forbidden in FORBIDDEN_BOILERPLATES:
            self.assertNotIn(
                forbidden,
                summary,
                f"Regression failure: Found static boilerplate '{forbidden}' in summary for: {claim_label}"
            )
        self.assertIn(result.get("verdict"), ["REAL", "FAKE"], f"Verdict must be strictly REAL or FAKE for: {claim_label}")

    def test_geopolitical_error_no_boilerplate(self):
        result = predict("India is a state")
        self.assert_no_boilerplate(result, "India is a state")
        self.assertEqual(result["verdict"], "FAKE")
        self.assertIn("sovereign", result["summary"].lower())

    def test_unlisted_factual_claim_dynamic_summary(self):
        claim = "NASA scientists deployed the James Webb Space Telescope to study distant exoplanets"
        result = predict(claim, check_cross_sources=False)
        self.assert_no_boilerplate(result, claim)
        self.assertEqual(result["verdict"], "REAL")
        self.assertIn("telescope", result["summary"].lower())

    def test_unlisted_viral_hoax_dynamic_summary(self):
        claim = "Secret hack: rubbing garlic on vehicle windshield prevents cracks during hail storm"
        result = predict(claim, check_cross_sources=False)
        self.assert_no_boilerplate(result, claim)
        self.assertEqual(result["verdict"], "FAKE")
        self.assertIn("garlic", result["summary"].lower())

    def test_document_multi_paragraph_summary(self):
        doc = (
            "NEW DELHI -- The Indian Space Research Organisation announced the successful insertion "
            "of the Aditya-L1 spacecraft into a halo orbit around the Sun-Earth L1 point. "
            "The mission will study solar flares and coronal mass ejections over the next five years. "
            "Officials confirmed all scientific payloads are functioning normally."
        )
        result = predict(doc, check_cross_sources=False)
        self.assert_no_boilerplate(result, "Aditya-L1 Document")
        self.assertEqual(result["verdict"], "REAL")
        # Summary or key findings should cite the mission or spacecraft
        combined = (result["summary"] + " " + " ".join(result.get("keyFindings", []))).lower()
        self.assertTrue("aditya" in combined or "space" in combined or "mission" in combined)

    def test_medical_myth_dynamic_summary(self):
        claim = "Drinking boiled lemon water and honey cures Stage 4 cancer in three days"
        result = predict(claim, check_cross_sources=False)
        self.assert_no_boilerplate(result, claim)
        self.assertEqual(result["verdict"], "FAKE")
        self.assertIn("cancer", result["summary"].lower())

if __name__ == "__main__":
    unittest.main()
