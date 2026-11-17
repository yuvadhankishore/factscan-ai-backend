# -*- coding: utf-8 -*-
"""
Multi-Source Simulation Engine - Offline & Air-Gapped Fallback
Synthesizes contextual wire-service articles (Reuters, AP, AFP, BBC, FactCheck.org)
when live internet search is blocked, throttled, or running in an offline test harness.
"""

import re
import hashlib
from typing import List, Dict, Any

class MultiSourceSimulator:
    WIRES = [
        {"publisher": "Reuters", "domain": "reuters.com", "tier": "TIER_1_WIRE", "credibility": 98.0},
        {"publisher": "Associated Press", "domain": "apnews.com", "tier": "TIER_1_WIRE", "credibility": 97.5},
        {"publisher": "BBC News", "domain": "bbc.com", "tier": "HIGH_FACTUALITY", "credibility": 94.0},
        {"publisher": "Agence France-Presse", "domain": "afp.com", "tier": "TIER_1_WIRE", "credibility": 96.0},
        {"publisher": "Snopes Fact Check", "domain": "snopes.com", "tier": "FACT_CHECKER", "credibility": 93.0},
    ]

    @classmethod
    def extract_keywords(cls, text: str) -> List[str]:
        words = re.findall(r'[A-Za-z0-9]+', text)
        stopwords = {
            'the', 'is', 'at', 'which', 'on', 'a', 'an', 'and', 'or', 'in', 'for', 'of',
            'with', 'to', 'from', 'by', 'that', 'this', 'it', 'as', 'be', 'are', 'was',
            'were', 'will', 'have', 'has', 'had', 'do', 'does', 'did', 'not', 'but', 'news'
        }
        return [w for w in words if w.lower() not in stopwords and len(w) > 3]

    @classmethod
    def simulate_cross_sources(cls, claim: str, is_real: bool = True) -> List[Dict[str, Any]]:
        keywords = cls.extract_keywords(claim)
        subject = " ".join(keywords[:3]) if keywords else "Reported Event"
        articles = []
        seed = int(hashlib.md5(claim.encode('utf-8')).hexdigest(), 16) % 1000

        for i, wire in enumerate(cls.WIRES[:4]):
            stance = "CORROBORATES" if is_real else ("REFUTES" if i < 2 else "NEUTRAL_COVERAGE")
            similarity = 0.85 if stance == "CORROBORATES" else (0.78 if stance == "REFUTES" else 0.55)

            if stance == "CORROBORATES":
                headline = f"Official Confirmation: Independent verification of {subject}"
                snippet = (f"{wire['publisher']} correspondents and official records confirm key claims regarding {subject}. "
                           f"Primary documents and accredited spokespersons corroborate the sequence of events without discrepancy.")
            elif stance == "REFUTES":
                headline = f"Fact Check: Viral reports regarding {subject} lack evidentiary backing"
                snippet = (f"An investigation by {wire['publisher']} found no supporting documentation or official statements "
                           f"substantiating claims about {subject}. Independent agencies categorize the viral claim as unverified.")
            else:
                headline = f"Monitoring Developments: Cross-agency background report on {subject}"
                snippet = (f"{wire['publisher']} is tracking statements related to {subject}. Contextual analysis indicates "
                           f"ongoing investigations by regulatory bodies and relevant industry experts.")

            articles.append({
                "publisher": wire["publisher"],
                "domain": wire["domain"],
                "tier": wire["tier"],
                "credibility": wire["credibility"],
                "title": headline,
                "url": f"https://www.{wire['domain']}/news/archive-{seed + i}",
                "snippet": snippet,
                "stance": stance,
                "similarity": similarity,
                "is_simulated": True
            })

        return articles
