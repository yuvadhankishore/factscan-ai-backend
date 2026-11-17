#!/usr/bin/env python3
"""
FactScan AI - Quotation Verification & Attribution Audit Module
Extracts direct and indirect quotes and audits the authenticity of named attributions.
"""

import re
from typing import List, Dict, Any

ANONYMOUS_ATTRIBUTION = [
    r"\b(unnamed\s+sources?|sources\s+close\s+to|anonymous\s+insider|someone\s+close\s+to)\b",
    r"\b(insiders\s+claim|sources\s+say|viral\s+video\s+claims|people\s+are\s+saying)\b",
    r"\b(it\s+has\s+been\s+reported\s+that|word\s+on\s+the\s+street)\b"
]

def audit_quotations_and_attribution(text: str) -> Dict[str, Any]:
    """
    Scans text for quotations, speakers, and identifies vague or unverified attribution patterns.
    """
    # 1. Direct Quotes Extraction
    quote_matches = re.findall(r'["\u201c\u201d]([^"\u201c\u201d]{15,250})["\u201c\u201d]', text)
    
    # 2. Indirect Attribution Phrases
    attr_matches = re.findall(r'(according\s+to\s+[^,.]+|(?:stated|announced|confirmed|reported)\s+by\s+[^,.]+)', text, flags=re.IGNORECASE)

    # 3. Anonymous/Vague Attribution Detection
    text_lower = text.lower()
    has_anonymous_sources = any(bool(re.search(pat, text_lower)) for pat in ANONYMOUS_ATTRIBUTION)

    audit_status = "VERIFIED_ATTRIBUTION"
    if has_anonymous_sources:
        audit_status = "UNVERIFIED_ANONYMOUS_LEAK"
    elif not quote_matches and not attr_matches:
        audit_status = "NO_ATTRIBUTED_QUOTES"
    elif quote_matches:
        audit_status = "DIRECT_QUOTES_PRESENT"

    return {
        "directQuotesCount": len(quote_matches),
        "extractedQuotes": quote_matches[:3],
        "attributionPhrases": [a.strip() for a in attr_matches[:3]],
        "hasAnonymousSources": has_anonymous_sources,
        "attributionStatus": audit_status,
        "credibilityImpact": -15 if has_anonymous_sources else (10 if len(quote_matches) > 0 else 0)
    }

if __name__ == "__main__":
    sample = 'NASA Administrator stated "James Webb has opened a new window onto the cosmos" according to official press releases.'
    print(audit_quotations_and_attribution(sample))
    
    sample_fake = 'Sources close to government insiders claim "all bank accounts will be blocked tonight".'
    print(audit_quotations_and_attribution(sample_fake))
