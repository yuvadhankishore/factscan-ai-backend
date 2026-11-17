# -*- coding: utf-8 -*-
"""
FactScan AI - Wikipedia Knowledge & Entity Verifier
Fetches official entity definitions, summaries, and disambiguations via Wikipedia REST API.
Cross-references claims against canonical encyclopedic knowledge.
"""

import urllib.request
import urllib.parse
import json
import re
from typing import Dict, Any, Optional

class WikipediaVerifier:
    API_URL = "https://en.wikipedia.org/api/rest_v1/page/summary/"

    @classmethod
    def fetch_entity_summary(cls, entity_name: str) -> Optional[Dict[str, Any]]:
        clean_name = entity_name.strip().replace(" ", "_")
        encoded = urllib.parse.quote(clean_name)
        url = f"{cls.API_URL}{encoded}"

        req = urllib.request.Request(
            url,
            headers={
                "User-Agent": "FactScanAI/2.0 (https://github.com/yuvadhankishore/FactScanAI; contact@factscan.internal) Educational/FactChecking"
            }
        )

        try:
            with urllib.request.urlopen(req, timeout=4) as response:
                if response.status == 200:
                    data = json.loads(response.read().decode('utf-8'))
                    return {
                        "title": data.get("title", ""),
                        "description": data.get("description", ""),
                        "extract": data.get("extract", ""),
                        "url": data.get("content_urls", {}).get("desktop", {}).get("page", ""),
                        "thumbnail": data.get("thumbnail", {}).get("source", "")
                    }
        except Exception:
            return None
        return None

    @classmethod
    def verify_against_wikipedia(cls, claim_text: str) -> Optional[Dict[str, Any]]:
        """
        Extracts primary noun phrase from claim, queries Wikipedia, and checks for direct classification conflict.
        """
        words = re.findall(r'\b[A-Za-z0-9]+\b', claim_text)
        if not words:
            return None

        # Try first 1-2 words as candidate entity
        candidates = [words[0]]
        if len(words) >= 2:
            candidates.append(f"{words[0]} {words[1]}")

        for cand in candidates:
            wiki = cls.fetch_entity_summary(cand)
            if not wiki or not wiki.get("extract"):
                continue

            desc = wiki.get("description", "").lower()
            extract = wiki.get("extract", "").lower()
            claim_lower = claim_text.lower()

            # Check Country claimed as State
            if "country" in desc or "country in" in extract:
                if re.search(r'\b(is\s+)?(a\s+)?state\b', claim_lower):
                    return {
                        "isContradiction": True,
                        "entity": wiki["title"],
                        "canonicalType": wiki.get("description") or "Sovereign Country",
                        "claimedType": "State",
                        "summary": f"According to verified encyclopedic records, {wiki['title']} is a {wiki.get('description', 'country')}. Claiming it is a state directly contradicts canonical facts.",
                        "wikipediaUrl": wiki["url"],
                        "extractSnippet": wiki["extract"][:250] + "..."
                    }

            # Check City claimed as Country
            if "city" in desc or "capital of" in desc or "capital city" in extract:
                if re.search(r'\b(is\s+)?(a\s+)?country\b', claim_lower):
                    return {
                        "isContradiction": True,
                        "entity": wiki["title"],
                        "canonicalType": wiki.get("description") or "City",
                        "claimedType": "Country",
                        "summary": f"Encyclopedic records confirm {wiki['title']} is a {wiki.get('description', 'city')}, not a sovereign nation.",
                        "wikipediaUrl": wiki["url"],
                        "extractSnippet": wiki["extract"][:250] + "..."
                    }

        return None
