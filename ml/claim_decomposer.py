#!/usr/bin/env python3
"""
FactScan AI - Atomic Claim Decomposition Engine
Deconstructs complex, multifaceted news articles into verifiable atomic propositions.
"""

import re
from typing import List, Dict, Any

OPINION_MARKERS = [
    r"\b(i\s+think|in\s+my\s+opinion|we\s+believe|probably|maybe|perhaps|it\s+seems)\b",
    r"\b(personally|from\s+my\s+perspective|as\s+far\s+as\s+i\s+know)\b"
]

def split_into_candidate_sentences(text: str) -> List[str]:
    """
    Splits text on sentence boundaries and bullet points.
    """
    clean = re.sub(r'[\r\n]+', ' ', text).strip()
    sentences = re.split(r'(?<=[.!?])\s+', clean)
    return [s.strip() for s in sentences if len(s.strip()) > 20]

def is_verifiable_proposition(sentence: str) -> bool:
    """
    Checks if a sentence contains a concrete factual proposition rather than pure rhetoric.
    """
    lower = sentence.lower()
    if any(re.search(pat, lower) for pat in OPINION_MARKERS):
        return False
        
    # Must contain a verb or key predicate
    has_predicate = bool(re.search(r'\b(is|was|were|are|has|have|had|declared|announced|stated|confirmed|found|discovered|passed|signed|approved|caused|cures|killed|issued|banned)\b', lower))
    return has_predicate and len(sentence.split()) >= 5

def decompose_article_claims(text: str, max_claims: int = 4) -> List[Dict[str, Any]]:
    """
    Decomposes text into atomic factual propositions.
    """
    sentences = split_into_candidate_sentences(text)
    atomic_claims = []
    
    for s in sentences:
        if is_verifiable_proposition(s):
            # Check for high-risk markers inside sub-claim
            is_sensational = bool(re.search(r'\b(shocking|miracle|cure|instantly|secret|hoax|destroyed|banned|unbelievable)\b', s.lower()))
            atomic_claims.append({
                "claimText": s,
                "isSensational": is_sensational,
                "complexity": "Compound" if " and " in s or " while " in s else "Atomic"
            })
            if len(atomic_claims) >= max_claims:
                break
                
    if not atomic_claims and text.strip():
        atomic_claims.append({
            "claimText": text.strip()[:150],
            "isSensational": False,
            "complexity": "Primary"
        })
        
    return atomic_claims

if __name__ == "__main__":
    sample_text = (
        "ISRO launched the Aditya-L1 solar observation spacecraft last month. "
        "Scientists confirmed the coronagraph payload is now transmitting high resolution imagery. "
        "Some social media users claimed the satellite took photos of alien structures on the Sun."
    )
    print(decompose_article_claims(sample_text))
