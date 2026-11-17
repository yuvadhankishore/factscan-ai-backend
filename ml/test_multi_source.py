# -*- coding: utf-8 -*-
"""
Automated Test Suite for FactScan AI Multi-Source News Verification Pipeline
"""

import sys
import unittest
from pathlib import Path

# Add ml folder to path
sys.path.insert(0, str(Path(__file__).parent))

from publisher_registry import get_publisher_rating
from stance_analyzer import analyze_article_stance
from consensus_scorer import compute_multi_source_consensus
from claim_decomposer import decompose_article_claims
from quote_verifier import audit_quotations_and_attribution
from temporal_detector import analyze_temporal_signals
from multi_source_simulator import MultiSourceSimulator

class TestMultiSourcePipeline(unittest.TestCase):
    def test_publisher_registry(self):
        wire_rating = get_publisher_rating("reuters.com")
        self.assertEqual(wire_rating["tier"], "WIRE_SERVICE")
        self.assertGreater(wire_rating["trustScore"], 90)

        fact_checker = get_publisher_rating("snopes.com")
        self.assertEqual(fact_checker["tier"], "FACT_CHECKER")

        tabloid = get_publisher_rating("dailymail.co.uk")
        self.assertEqual(tabloid["tier"], "MIXED_TABLOID")
        self.assertLess(tabloid["trustScore"], 70)

        satire = get_publisher_rating("theonion.com")
        self.assertEqual(satire["tier"], "SATIRE")
        self.assertLess(satire["trustScore"], 20)

    def test_stance_analyzer(self):
        claim = "NASA confirms 2024 was warmest year on global meteorological record."
        art_corrob = {
            "title": "NASA and NOAA scientists officially confirm 2024 was warmest year",
            "snippet": "Verified findings corroborate that 2024 global temperatures surpassed historical records."
        }
        art_refute = {
            "title": "Viral rumor claiming NASA confirmed 2024 warmth record is a debunked hoax",
            "snippet": "Fact-checkers have debunked the false and unfounded rumor about climate statistics."
        }

        stance_c = analyze_article_stance(claim, art_corrob)
        self.assertEqual(stance_c["stance"], "CORROBORATES")

        stance_r = analyze_article_stance(claim, art_refute)
        self.assertEqual(stance_r["stance"], "REFUTES")

    def test_consensus_scorer(self):
        claim = "Major space agency confirms satellite launch"
        sources = [
            {"publisher": "Reuters", "stance": "CORROBORATES", "publisherTrustScore": 99},
            {"publisher": "Associated Press", "stance": "CORROBORATES", "publisherTrustScore": 99},
            {"publisher": "BBC News", "stance": "CORROBORATES", "publisherTrustScore": 95},
        ]
        consensus = compute_multi_source_consensus(claim, sources)
        self.assertGreater(consensus["consensusScore"], 80.0)
        self.assertIn("CORROBORATED", consensus["verdict"])
        self.assertEqual(consensus["corroboratingCount"], 3)
        self.assertEqual(consensus["refutingCount"], 0)

    def test_claim_decomposer(self):
        paragraph = "Scientists at CERN discovered a new subatomic particle yesterday. Meanwhile, the stock market plunged 500 points in Tokyo."
        claims = decompose_article_claims(paragraph)
        self.assertGreaterEqual(len(claims), 2)
        self.assertTrue(any("CERN" in c["claimText"] for c in claims))

    def test_quote_verifier(self):
        text_with_quote = 'The Prime Minister announced: "We will allocate fifty billion dollars toward renewable solar infrastructure."'
        audit = audit_quotations_and_attribution(text_with_quote)
        self.assertGreaterEqual(audit["directQuotesCount"], 1)

    def test_temporal_detector(self):
        old_text = "BREAKING ALERT: The Federal Reserve slashed interest rates to zero during the emergency session in 2012!"
        temporal = analyze_temporal_signals(old_text)
        self.assertTrue(temporal["isRecycledOldNews"])

    def test_multi_source_simulator(self):
        simulated = MultiSourceSimulator.simulate_cross_sources("New quantum computing breakthrough unveiled", is_real=True)
        self.assertGreaterEqual(len(simulated), 3)
        self.assertTrue(any(s["stance"] == "CORROBORATES" for s in simulated))

if __name__ == "__main__":
    unittest.main()
