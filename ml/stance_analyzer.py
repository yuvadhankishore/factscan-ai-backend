#!/usr/bin/env python3
"""
FactScan AI - Cross-Article Stance & Semantic Alignment Analyzer
Determines whether external web articles corroborate, refute, or neutrally report
on a submitted news claim.
"""

import re
from typing import Dict, Any, List

REFUTATION_SIGNALS = [
    r"\b(false|hoax|fake|debunk(ed|s)?|unfounded|myth|untrue|fabricated)\b",
    r"\b(no\s+evidence|lacks?\s+evidence|disproven|disproved|dismissed)\b",
    r"\b(misleading|inaccurate|rumor|scam|fact[- ]check)\b",
    r"\b(denied|refutes?|falsely\s+claims?|not\s+true)\b"
]

CORROBORATION_SIGNALS = [
    r"\b(confirms?|confirmed|announces?|announced|official(ly)?)\b",
    r"\b(verified|corroborated|documented|published\s+findings)\b",
    r"\b(approved|successful(ly)?|discovered|revealed|reported)\b",
    r"\b(statement\s+by|press\s+release|accord(ing)?\s+to)\b"
]

def analyze_article_stance(claim_text: str, article: Dict[str, Any]) -> Dict[str, Any]:
    """
    Evaluates semantic relationship between the input claim and an external news article.
    """
    claim_lower = claim_text.lower()
    art_title = str(article.get("title", "")).lower()
    art_snippet = str(article.get("snippet", "") or article.get("bodySnippet", "")).lower()
    combined_art = f"{art_title} {art_snippet}"

    # Extract words to compute lexical overlap
    claim_words = set(re.findall(r'\b\w{3,}\b', claim_lower))
    art_words = set(re.findall(r'\b\w{3,}\b', combined_art))
    
    overlap = len(claim_words.intersection(art_words))
    overlap_ratio = overlap / max(len(claim_words), 1)

    # Check refutation vs corroboration
    refute_matches = sum(1 for pat in REFUTATION_SIGNALS if re.search(pat, combined_art))
    corrob_matches = sum(1 for pat in CORROBORATION_SIGNALS if re.search(pat, combined_art))

    # Check for entity classification contradictions (e.g. claim says 'state', article says 'country')
    entity_contradiction = False
    if "state" in claim_words and ("country" in art_words or "nation" in art_words or "sovereign" in art_words):
        entity_contradiction = True
    elif "country" in claim_words and ("city" in art_words or "capital" in art_words or "prefecture" in art_words):
        entity_contradiction = True

    if entity_contradiction and overlap_ratio >= 0.10:
        stance = "REFUTES"
        confidence = 95
        explanation = "Article defines entity with a contradictory sovereign or geographic classification."
    elif refute_matches > 0 and ("debunk" in combined_art or "false" in combined_art or "hoax" in combined_art):
        stance = "REFUTES"
        confidence = min(98, 75 + (refute_matches * 8))
        explanation = "Article explicitly debunks or reports this assertion as false/fabricated."
    elif corrob_matches >= 2 and overlap_ratio >= 0.25:
        stance = "CORROBORATES"
        confidence = min(96, 70 + (corrob_matches * 6))
        explanation = "Article independently corroborates the factual announcement or event."
    elif corrob_matches >= 1 and overlap_ratio >= 0.20:
        stance = "CORROBORATES"
        confidence = 82
        explanation = "Article reports matching facts and statements from authorized sources."
    elif overlap_ratio >= 0.15:
        stance = "NEUTRAL_COVERAGE"
        confidence = 75
        explanation = "Article discusses the subject matter without making an explicit debunking or endorsement."
    else:
        stance = "BACKGROUND_CONTEXT"
        confidence = 60
        explanation = "Article provides adjacent background context related to entities in the claim."

    return {
        "stance": stance,
        "alignmentScore": int(round(overlap_ratio * 100)),
        "stanceConfidence": confidence,
        "explanation": explanation
    }

if __name__ == "__main__":
    claim = "WHO announces lemons cure cancer"
    art_debunk = {"title": "Fact Check: WHO did NOT say lemons cure cancer, claims are false", "snippet": "Viral posts falsely claim lemon juice cures tumors."}
    art_real = {"title": "WHO announces childhood immunization guidelines", "snippet": "Official release confirms global vaccine expansion."}
    print("Debunk stance:", analyze_article_stance(claim, art_debunk))
    print("Real stance:", analyze_article_stance(claim, art_real))

