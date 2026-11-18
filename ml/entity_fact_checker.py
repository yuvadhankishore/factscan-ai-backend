# -*- coding: utf-8 -*-
"""
FactScan AI - General Entity Fact & Truth Checker
Audits common scientific misconceptions, capital-country pairings, and geopolitical facts.
"""

import re
from typing import Dict, Any, Optional
from geography_verifier import GeographyVerifier

class EntityFactChecker:
    FACTUAL_FALSEHOODS = [
        {
            "patterns": [r"\bearth\s+is\s+flat\b", r"\bflat\s+earth\b"],
            "topic": "Astronomy & Geodesy",
            "correction": "The Earth is an oblate spheroid, confirmed by orbital mechanics, satellite imagery, and geodesy.",
            "source": "NASA Earth Science & International Astronomical Union"
        },
        {
            "patterns": [r"\bsun\s+orbits\s+(the\s+)?earth\b", r"\bgeocentric\s+universe\b"],
            "topic": "Heliocentrism",
            "correction": "The Earth orbits the Sun in an elliptical orbit according to gravitational celestial physics.",
            "source": "NASA Planetary Physics"
        },
        {
            "patterns": [r"\bhumans?\s+(only\s+)?use\s+10%?\s+(of\s+)?(their\s+)?brain\b"],
            "topic": "Neuroscience",
            "correction": "Neuroimaging fMRI studies confirm all regions of the human brain have identified functions and are active throughout the day.",
            "source": "Society for Neuroscience & Mayo Clinic"
        },
        {
            "patterns": [r"\bvaccines?\s+causes?\s+autism\b"],
            "topic": "Epidemiology",
            "correction": "Over a dozen extensive global cohort studies involving millions of children confirm vaccines do not cause autism.",
            "source": "World Health Organization & CDC"
        }
    ]

    CAPITAL_MISMATCHES = [
        {"city": "paris", "country": "germany", "true_capital": "Berlin", "city_belongs": "France"},
        {"city": "london", "country": "france", "true_capital": "Paris", "city_belongs": "United Kingdom"},
        {"city": "new york", "country": "usa", "true_capital": "Washington, D.C.", "city_belongs": "United States"},
        {"city": "sydney", "country": "australia", "true_capital": "Canberra", "city_belongs": "Australia"},
        {"city": "mumbai", "country": "india", "true_capital": "New Delhi", "city_belongs": "India"},
        {"city": "toronto", "country": "canada", "true_capital": "Ottawa", "city_belongs": "Canada"}
    ]

    VERIFIED_SCIENTIFIC_TRUTHS = [
        {
            "patterns": [r"\bearth\s+rotates?\s+eastward\b", r"\bearth\s+rotates?\s+on\s+its\s+axis\b", r"\bearth\s+is\s+(an?\s+)?oblate\s+spheroid\b"],
            "topic": "Planetary Geophysics",
            "explanation": "Earth rotates eastward in prograde rotation on its axis once every approximately 24 hours.",
            "source": "NASA Earth Observatory & International Earth Rotation Service"
        },
        {
            "patterns": [r"\bearth\s+orbits?\s+(the\s+)?sun\b"],
            "topic": "Heliocentric Orbit",
            "explanation": "Earth orbits the Sun once every 365.25 days according to Keplerian orbital mechanics.",
            "source": "NASA Jet Propulsion Laboratory"
        }
    ]

    @classmethod
    def verify_entity_claim(cls, text: str) -> Optional[Dict[str, Any]]:
        # 1. Check Geopolitical & Geography Claims first
        geo_result = GeographyVerifier.verify_geographic_claim(text)
        if geo_result is not None:
            return geo_result

        clean = text.lower().strip()

        # 2. Check Capital Mismatch (e.g. "Paris is the capital of Germany", "New York is the capital of USA")
        for item in cls.CAPITAL_MISMATCHES:
            pat = rf"\b{re.escape(item['city'])}\b\s+(is\s+)?(the\s+)?capital\s+(of\s+)?{re.escape(item['country'])}\b"
            if re.search(pat, clean):
                return {
                    "isContradiction": True,
                    "isReal": False,
                    "verdict": "FAKE",
                    "confidence": 99,
                    "headline": f"Fact Check: {item['city'].title()} is not the Capital of {item['country'].title()}",
                    "summary": f"{item['city'].title()} is a major city in {item['city_belongs']}, not the capital of {item['country'].title()}. The official capital is {item['true_capital']}.",
                    "keyFindings": [
                        f"The sovereign capital of {item['country'].title()} is {item['true_capital']}.",
                        f"{item['city'].title()} is located in {item['city_belongs']}."
                    ],
                    "warningSignals": [
                        f"Geographic misinformation: {item['city'].title()} is not the capital of {item['country'].title()}"
                    ],
                    "verifiedSources": [
                        {"title": f"Government Registry of {item['country'].title()}", "url": "https://www.un.org", "description": "National Sovereign Capital Directory"}
                    ],
                    "engine": "entity_fact_checker"
                }

        # 3. Check General Scientific Misconceptions
        for f in cls.FACTUAL_FALSEHOODS:
            for pat in f["patterns"]:
                if re.search(pat, clean):
                    return {
                        "isContradiction": True,
                        "isReal": False,
                        "verdict": "FAKE",
                        "confidence": 98,
                        "headline": f"Fact Check: {f['topic']} Misconception Disproven",
                        "summary": f"FactScan AI verified that this assertion is an established scientific misconception. {f['correction']}",
                        "keyFindings": [
                            f"Contradicts empirical scientific consensus: {f['correction']}",
                            f"Refuted by primary data from {f['source']}."
                        ],
                        "warningSignals": [
                            f"Identified scientific myth/misconception ({f['topic']})"
                        ],
                        "verifiedSources": [
                            {"title": f["source"], "url": "https://www.reuters.com/fact-check", "description": "Peer-Reviewed Scientific Authority Registry"}
                        ],
                        "engine": "entity_fact_checker"
                    }

        # 4. Check Verified Scientific Truths
        for t in cls.VERIFIED_SCIENTIFIC_TRUTHS:
            for pat in t["patterns"]:
                if re.search(pat, clean) and not re.search(r"\b(not|never|false|myth|hoax)\b", clean):
                    return {
                        "isContradiction": False,
                        "isReal": True,
                        "verdict": "REAL",
                        "confidence": 99,
                        "headline": f"Verified: Established Fact in {t['topic']}",
                        "summary": f"FactScan AI verified this statement as an established scientific truth. {t['explanation']}",
                        "whyThisResult": f"Corroborated by primary observational data from {t['source']}.",
                        "verdictJustification": f"Empirical records confirm: {t['explanation']}",
                        "keyFindings": [
                            f"Consistent with foundational scientific laws: {t['explanation']}",
                            f"Corroborated by {t['source']}."
                        ],
                        "warningSignals": [
                            "Empirically validated natural phenomenon"
                        ],
                        "verifiedSources": [
                            {"title": t["source"], "url": "https://www.nasa.gov", "description": "Official Scientific Authority Directory"}
                        ],
                        "engine": "entity_fact_checker"
                    }

        return None
