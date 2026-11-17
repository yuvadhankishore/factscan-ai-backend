#!/usr/bin/env python3
"""
FactScan AI - Multi-Source Global Consensus Scoring Algorithm
Weights stance classifications by publisher credibility tiers to synthesize
an empirical global consensus score.
"""

from typing import List, Dict, Any

def compute_multi_source_consensus(
    claim_text: str,
    articles_with_stance: List[Dict[str, Any]]
) -> Dict[str, Any]:
    """
    Synthesizes multi-source consensus metrics across analyzed articles.
    """
    if not articles_with_stance:
        return {
            "totalSources": 0,
            "corroboratingCount": 0,
            "refutingCount": 0,
            "neutralCount": 0,
            "consensusScore": 50,
            "verdict": "UNVERIFIED_INSUFFICIENT_SOURCES",
            "summary": "No verified external news wire coverage was found for this specific claim phrasing."
        }

    total_weight = 0.0
    support_weight = 0.0
    refute_weight = 0.0

    corrob_count = 0
    refute_count = 0
    neutral_count = 0
    corroborating_publishers = []
    refuting_publishers = []

    for art in articles_with_stance:
        stance = art.get("stance", "NEUTRAL_COVERAGE")
        trust = float(art.get("publisherTrustScore", 70))
        pub_name = art.get("publisher", "Unknown Publisher")

        # Weight scale based on trust score
        w = max(0.2, (trust / 100.0) ** 1.5)
        total_weight += w

        if stance == "CORROBORATES":
            corrob_count += 1
            support_weight += w
            if pub_name not in corroborating_publishers:
                corroborating_publishers.append(pub_name)
        elif stance == "REFUTES":
            refute_count += 1
            refute_weight += (w * 1.5) # Refutation signals from trusted outlets have high evidentiary weight
            if pub_name not in refuting_publishers:
                refuting_publishers.append(pub_name)
        else:
            neutral_count += 1
            support_weight += (w * 0.4) # Neutral mentions slightly increase probability of authentic subject matter

    if total_weight > 0:
        ratio = max(0.0, min(1.0, (support_weight - refute_weight + (total_weight * 0.5)) / total_weight))
        consensus_score = int(round(ratio * 100))
    else:
        consensus_score = 50

    if refute_count >= 1 and refute_weight > support_weight:
        verdict = "DISPUTED_OR_DEBUNKED"
        summary = f"Flagged as false or debunked across {refute_count} independent sources (including {', '.join(refuting_publishers[:2])})."
    elif corrob_count >= 2:
        verdict = "STRONGLY_CORROBORATED"
        summary = f"Corroborated by {corrob_count} accredited international publishers (including {', '.join(corroborating_publishers[:2])})."
    elif corrob_count == 1:
        verdict = "PARTIALLY_CORROBORATED"
        summary = f"Reported by {corroborating_publishers[0]} with consistent background coverage."
    elif neutral_count > 0:
        verdict = "INDEPENDENT_REPORTING"
        summary = f"Mentioned in news coverage across {neutral_count} outlets without explicit debunking."
    else:
        verdict = "UNVERIFIED"
        summary = "No authoritative confirmation located in global news registries."

    return {
        "totalSources": len(articles_with_stance),
        "corroboratingCount": corrob_count,
        "refutingCount": refute_count,
        "neutralCount": neutral_count,
        "consensusScore": consensus_score,
        "verdict": verdict,
        "summary": summary,
        "corroboratingPublishers": corroborating_publishers[:4],
        "refutingPublishers": refuting_publishers[:4]
    }
