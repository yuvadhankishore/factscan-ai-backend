#!/usr/bin/env python3
"""
FactScan AI - Temporal Anomaly & Outdated News Recycling Detector
Detects historical news stories repurposed and circulated as breaking current events.
"""

import re
import datetime
from typing import Dict, Any

CURRENT_YEAR = 2026

def analyze_temporal_signals(text: str) -> Dict[str, Any]:
    """
    Extracts dates, years, and checks whether text contains outdated temporal markers.
    """
    text_lower = text.lower()
    
    # Extract 4-digit years between 1990 and 2030
    years_found = [int(y) for y in re.findall(r'\b(19\d{2}|20[0-3]\d)\b', text)]
    years_distinct = sorted(list(set(years_found)))

    # Check for urgent recency framing
    has_breaking_claim = bool(re.search(r'\b(breaking|just\s+in|urgent\s+alert|happening\s+now|today|minutes\s+ago)\b', text_lower))

    # Evaluate temporal status
    is_recycled_old_news = False
    oldest_year = years_distinct[0] if years_distinct else None
    
    if has_breaking_claim and oldest_year and (CURRENT_YEAR - oldest_year) >= 3:
        is_recycled_old_news = True
        explanation = f"Text uses breaking/current phrasing but references historical events from {oldest_year}."
    elif oldest_year and oldest_year < 2023:
        explanation = f"References historical records from {oldest_year}; verify if context remains current."
    else:
        explanation = "Temporal context aligns with contemporaneous news reporting."

    return {
        "detectedYears": years_distinct,
        "isRecycledOldNews": is_recycled_old_news,
        "hasBreakingFraming": has_breaking_claim,
        "currentBaselineYear": CURRENT_YEAR,
        "temporalAuditExplanation": explanation
    }

if __name__ == "__main__":
    sample = "BREAKING ALERT: Major flood hits downtown metro area causing massive evacuations in 2019!"
    print(analyze_temporal_signals(sample))
