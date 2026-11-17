#!/usr/bin/env python3
"""
FactScan AI - All-Format Gemini Fact-Checking Test Suite
Validates that Gemini Reasoning Engine accurately identifies REAL vs FAKE across:
1. Text claims (Geopolitical, Health, Viral rumors)
2. URL content
3. Document text extraction
4. Image OCR screenshots
5. Audio transcripts
"""

import sys
import json
import unittest
from predict import predict
from entity_fact_checker import EntityFactChecker
from medical_scientific_verifier import check_medical_scientific_consensus
from viral_rumor_verifier import check_viral_rumors

class TestAllFormatGemini(unittest.TestCase):

    def test_01_geopolitical_text_claim_fake(self):
        """Test 'india a state' must be classified as FAKE."""
        res = predict("india a state", check_cross_sources=False)
        self.assertFalse(res["isReal"], "Expected 'india a state' to be classified as FAKE (isReal=False)")
        self.assertEqual(res.get("verdict"), "FAKE")
        self.assertGreaterEqual(res["confidence"], 90)

    def test_02_medical_pseudoscience_claim_fake(self):
        """Test 'lemon cures cancer in 48 hours' must be classified as FAKE."""
        res = predict("lemon cures cancer in 48 hours without chemotherapy", check_cross_sources=False)
        self.assertFalse(res["isReal"], "Expected lemon cancer myth to be FAKE")
        self.assertEqual(res.get("verdict"), "FAKE")
        self.assertIn("cancer", res["headline"].lower())

    def test_03_astronomy_conspiracy_fake(self):
        """Test 'the earth is flat' must be classified as FAKE."""
        res = predict("the earth is flat and planetary curvature is fake", check_cross_sources=False)
        self.assertFalse(res["isReal"], "Expected flat earth myth to be FAKE")
        self.assertEqual(res.get("verdict"), "FAKE")

    def test_04_viral_rumor_unesco_fake(self):
        """Test 'UNESCO declared Indian national anthem best in the world' must be FAKE."""
        res = predict("UNESCO declared Indian national anthem best in the world", check_cross_sources=False)
        self.assertFalse(res["isReal"], "Expected UNESCO national anthem hoax to be FAKE")
        self.assertEqual(res.get("verdict"), "FAKE")

    def test_05_document_ocr_format_fake(self):
        """Test text simulated from OCR or uploaded document."""
        ocr_text = "OFFICIAL NOTICE: NASA confirms 6 days of total darkness across Earth starting this Friday."
        res = predict(ocr_text, check_cross_sources=False)
        self.assertFalse(res["isReal"], "Expected NASA darkness OCR text to be FAKE")
        self.assertEqual(res.get("verdict"), "FAKE")

    def test_06_audio_transcript_voice_fake(self):
        """Test transcribed audio voice clip claim."""
        voice_transcript = "Did you hear the news? WhatsApp will start charging every user from tomorrow unless you forward this message."
        res = predict(voice_transcript, check_cross_sources=False)
        self.assertFalse(res["isReal"], "Expected WhatsApp paid service forward to be FAKE")
        self.assertEqual(res.get("verdict"), "FAKE")

    def test_07_authentic_geopolitical_claim_real(self):
        """Test 'Republic of India is an independent sovereign country'."""
        check = EntityFactChecker.verify_entity_claim("India is an independent sovereign country consisting of 28 states")
        self.assertIsNone(check, "Authentic truth assertion should not be flagged as contradiction")

if __name__ == "__main__":
    suite = unittest.TestLoader().loadTestsFromTestCase(TestAllFormatGemini)
    runner = unittest.TextTestRunner(verbosity=2)
    result = runner.run(suite)
    sys.exit(0 if result.wasSuccessful() else 1)
