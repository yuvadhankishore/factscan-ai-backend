#!/usr/bin/env python3
"""
FactScan AI - Rigorous Multi-Domain Accuracy Test Suite
Evaluates accuracy across 50 diverse claims spanning Geopolitics, Medicine,
Astronomy, Technology, Banking/Scams, Science, and World News.
"""

import os
import sys
import unittest

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
if BASE_DIR not in sys.path:
    sys.path.insert(0, BASE_DIR)

from predict import predict

class TestAccuracyBenchmarks(unittest.TestCase):

    def test_geopolitics_claims(self):
        cases = [
            ("India is a state", False),
            ("India is an independent sovereign country", True),
            ("California is a state of the United States", True),
            ("California is an independent sovereign nation with UN seat", False),
            ("France is a sovereign European nation", True),
            ("Paris is an independent sovereign nation", False),
            ("Japan is a sovereign country located in East Asia", True),
            ("Tokyo is an independent country", False),
            ("Germany is a sovereign federal republic", True),
            ("Bavaria is an independent country recognized by the UN", False),
            ("The United Kingdom is a sovereign country", True),
            ("London is an independent country", False),
            ("Canada is a sovereign nation in North America", True),
            ("Ontario is an independent country with a UN seat", False),
            ("Australia is an independent sovereign continent and nation", True),
            ("Sydney is an independent country", False),
            ("Brazil is a sovereign nation in South America", True),
            ("Sao Paulo is an independent sovereign country", False),
            ("Russia is a sovereign transcontinental nation", True),
            ("Siberia is an independent member state of the UN", False)
        ]
        for claim, expected in cases:
            res = predict(claim, check_cross_sources=False)
            self.assertEqual(res["isReal"], expected, f"Failed on claim: '{claim}', expected isReal={expected}, got {res['isReal']}")

    def test_medical_and_health_claims(self):
        cases = [
            ("Drinking boiled lemon water cures cancer in 48 hours", False),
            ("Drinking colloidal silver gives humans permanent immunity to viruses", False),
            ("COVID-19 vaccines contain 5G microchips", False),
            ("WHO confirms seasonal influenza vaccines protect against targeted strains", True),
            ("Insulin is a standard verified treatment for managing diabetes", True),
            ("Eating raw onions in socks draws out all toxins and cures illness overnight", False),
            ("Clinical trials demonstrate chemotherapy efficacy in reducing tumor sizes", True),
            ("Drinking chlorine dioxide cure all diseases including autism and cancer", False),
            ("Cardiovascular exercise and balanced diet reduce risk of coronary disease", True),
            ("Paracetamol tablets labeled P-500 contain Machupo deadly virus", False)
        ]
        for claim, expected in cases:
            res = predict(claim, check_cross_sources=False)
            self.assertEqual(res["isReal"], expected, f"Failed on claim: '{claim}', expected isReal={expected}, got {res['isReal']}")

    def test_astronomy_and_physics_claims(self):
        cases = [
            ("NASA confirms Earth will go dark for six days in December", False),
            ("Sun will rise from the west next Friday", False),
            ("NASA James Webb Space Telescope observed earliest galaxies formed after Big Bang", True),
            ("Moon will turn green for ninety minutes due to alignment with Uranus", False),
            ("Gravity will turn off on Earth for three seconds", False),
            ("Total solar eclipses are predictable astronomical events caused by lunar alignment", True),
            ("ESA Euclid space telescope captures high-resolution wide-field cosmos images", True),
            ("Planet Nibiru will collide with Earth next month confirmed by astronomers", False),
            ("Earth rotates eastward on its axis every 24 hours", True),
            ("NASA Perseverance rover collected core Martian rock samples in Jezero crater", True)
        ]
        for claim, expected in cases:
            res = predict(claim, check_cross_sources=False)
            self.assertEqual(res["isReal"], expected, f"Failed on claim: '{claim}', expected isReal={expected}, got {res['isReal']}")

    def test_technology_and_finance_claims(self):
        cases = [
            ("Recharge smartphone battery in 30 seconds by putting it in microwave", False),
            ("WhatsApp will become chargeable from tomorrow unless you forward to 20 people", False),
            ("Entering ATM PIN in reverse calls police and dispenses emergency cash", False),
            ("Reserve Bank of India 2000 rupee notes contain nano-GPS tracking chips", False),
            ("Federal Reserve regulates monetary policy and interest rates in the United States", True),
            ("IEEE standardizes Wi-Fi wireless networking protocols under 802.11 specifications", True),
            ("Government is giving free 50,000 cash to everyone who clicks this link", False),
            ("Phishing emails often utilize manufactured urgency and deceptive domain links", True),
            ("RBI issued guidelines requiring multi-factor authentication for digital payments", True),
            ("Dialing *#21# secretly transfers all your calls to government listening center", False)
        ]
        for claim, expected in cases:
            res = predict(claim, check_cross_sources=False)
            self.assertEqual(res["isReal"], expected, f"Failed on claim: '{claim}', expected isReal={expected}, got {res['isReal']}")

if __name__ == "__main__":
    unittest.main()

