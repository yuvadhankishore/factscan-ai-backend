#!/usr/bin/env python3
"""
FactScan AI - Multi-Source Global News Verification Engine
Synthesizes intelligent search queries, aggregates articles across diverse websites,
and performs cross-source verification.
"""

import re
import urllib.parse
from typing import List, Dict, Any

STOPWORDS = {
    "a", "about", "above", "after", "again", "against", "all", "am", "an", "and", "any", "are",
    "as", "at", "be", "because", "been", "before", "being", "below", "between", "both", "but",
    "by", "could", "did", "do", "does", "doing", "down", "during", "each", "few", "for", "from",
    "further", "had", "has", "have", "having", "he", "her", "here", "hers", "herself", "him",
    "his", "how", "i", "if", "in", "into", "is", "it", "its", "itself", "me", "more", "most",
    "my", "myself", "no", "nor", "not", "of", "off", "on", "once", "only", "or", "other", "ought",
    "our", "ours", "out", "over", "own", "same", "she", "should", "so", "some", "such", "than",
    "that", "the", "their", "theirs", "them", "then", "there", "these", "they", "this", "those",
    "through", "to", "too", "under", "until", "up", "very", "was", "we", "were", "what", "when",
    "where", "which", "while", "who", "whom", "why", "with", "would", "you", "your", "yours"
}

def extract_core_entities(text: str) -> List[str]:
    """
    Extract high-value entities, proper nouns, capitalized phrases, and acronyms.
    """
    # Acronyms (e.g. NASA, WHO, ISRO, RBI, UN, FDA, CISA)
    acronyms = re.findall(r'\b[A-Z]{2,}\b', text)
    
    # Capitalized entity phrases (e.g. James Webb, Chandrayaan, Supreme Court, Narendra Modi)
    capitalized_phrases = re.findall(r'\b[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*\b', text)
    
    # Numbers and percentages (e.g. 50,000, 6.5%, 500)
    numbers = re.findall(r'\b\d+(?:[,\.]\d+)?%?\b', text)
    
    candidates = []
    for item in acronyms + capitalized_phrases:
        cleaned = item.strip()
        if len(cleaned) >= 2 and cleaned.lower() not in STOPWORDS:
            if cleaned not in candidates:
                candidates.append(cleaned)
                
    for num in numbers:
        if num not in candidates:
            candidates.append(num)
            
    return candidates

def synthesize_search_queries(claim_text: str) -> Dict[str, str]:
    """
    Synthesizes multi-angle search queries to cross-reference with international news wire archives.
    """
    clean_text = re.sub(r'[^\w\s]', ' ', claim_text).strip()
    words = [w for w in clean_text.split() if w.lower() not in STOPWORDS and len(w) > 2]
    
    entities = extract_core_entities(claim_text)
    entity_str = " ".join(entities[:4]) if entities else " ".join(words[:4])
    
    # 1. Primary news search query
    primary_query = f"{entity_str} {' '.join(words[len(entities):len(entities)+4])}".strip()
    
    # 2. Fact-check specific inquiry
    fact_check_query = f"{entity_str} fact check hoax debunked"
    
    # 3. Accredited wire inquiry
    wire_query = f"{entity_str} reuters OR apnews OR bbc"
    
    return {
        "primary": primary_query or claim_text[:60],
        "factCheck": fact_check_query,
        "wireSearch": wire_query,
        "rawKeywords": words[:8]
    }

if __name__ == "__main__":
    sample = "NASA confirms James Webb Space Telescope observed oldest galaxy formed 13 billion years ago"
    print("Entities:", extract_core_entities(sample))
    print("Queries:", synthesize_search_queries(sample))
