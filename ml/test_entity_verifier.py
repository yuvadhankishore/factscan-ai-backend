# -*- coding: utf-8 -*-
"""
Unit Test Suite for Geopolitical & Entity Fact Verifier
Verifies detection of 'india a state', city-country confusion, and entity contradictions.
"""

import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))

from geography_verifier import GeographyVerifier
from entity_fact_checker import EntityFactChecker
from predict import predict

class TestEntityVerifier(unittest.TestCase):
    def test_india_a_state_contradiction(self):
        res = GeographyVerifier.verify_geographic_claim("india a state")
        self.assertIsNotNone(res)
        self.assertFalse(res["isReal"])
        self.assertEqual(res["verdict"], "FAKE")
        self.assertIn("Republic of India", res["entity"])
        self.assertEqual(res["confidence"], 99)

    def test_paris_is_a_country_contradiction(self):
        res = GeographyVerifier.verify_geographic_claim("Paris is a country")
        self.assertIsNotNone(res)
        self.assertFalse(res["isReal"])
        self.assertEqual(res["verdict"], "FAKE")
        self.assertEqual(res["claimedAs"], "Country")

    def test_africa_is_a_country_contradiction(self):
        res = GeographyVerifier.verify_geographic_claim("Africa is a country")
        self.assertIsNotNone(res)
        self.assertFalse(res["isReal"])
        self.assertEqual(res["verdict"], "FAKE")

    def test_capital_mismatch(self):
        res = EntityFactChecker.verify_entity_claim("Paris is the capital of Germany")
        self.assertIsNotNone(res)
        self.assertFalse(res["isReal"])
        self.assertEqual(res["verdict"], "FAKE")
        self.assertIn("Berlin", res["summary"])

    def test_scientific_misconception(self):
        res = EntityFactChecker.verify_entity_claim("The earth is flat")
        self.assertIsNotNone(res)
        self.assertFalse(res["isReal"])
        self.assertEqual(res["verdict"], "FAKE")

    def test_full_pipeline_india_a_state(self):
        output = predict("india a state")
        self.assertTrue(output["success"])
        self.assertFalse(output["isReal"])
        self.assertEqual(output["confidence"], 99)
        self.assertEqual(output["engine"], "geopolitical_entity_verifier")
        self.assertIn("Sovereign Country", output["summary"])

if __name__ == "__main__":
    unittest.main()
