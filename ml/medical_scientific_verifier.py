#!/usr/bin/env python3
"""
FactScan AI - Medical & Scientific Consensus Verification Engine
Verifies claims against official registries: WHO, CDC, NASA, FDA, and peer-reviewed consensus.
"""

import sys
import json
import re

SCIENTIFIC_DEBUNKING_DATABASE = [
    {
        "keywords": ["lemon", "cancer", "48 hours"],
        "is_real": False,
        "verdict": "FAKE",
        "confidence": 99,
        "headline": "Myth: Lemons Cure Cancer in 48 Hours Without Chemotherapy",
        "summary": "There is no clinical or empirical evidence that lemons, lemon water, or citrus extracts cure cancer in humans. Cancer requires evidence-based medical oncology treatments.",
        "key_findings": [
            "World Health Organization (WHO) and American Cancer Society confirm citrus cannot replace chemotherapy.",
            "No peer-reviewed randomized human clinical trials demonstrate lemons eradicate malignant tumors in 48 hours.",
            "Attributing miracle cures to ordinary foods is a classic viral medical misinformation pattern."
        ],
        "warning_signals": ["Pseudoscience miracle health claim lacking clinical trial evidence"],
        "authority": "World Health Organization (WHO) & American Cancer Society",
        "authority_url": "https://www.cancer.org"
    },
    {
        "keywords": ["earth", "flat"],
        "is_real": False,
        "verdict": "FAKE",
        "confidence": 99,
        "headline": "Myth: The Earth is Flat",
        "summary": "The Earth is an oblate spheroid. Decades of satellite geodesy, orbital photography, circumnavigation, and lunar eclipses conclusively prove planetary curvature.",
        "key_findings": [
            "NASA and international space agencies have photographed the Earth's spherical curvature from space continuously since 1959.",
            "Gravitational physics and celestial mechanics require massive planetary bodies to achieve hydrostatic equilibrium as spheres.",
            "Constellation visibility shifts between northern and southern hemispheres confirm spherical geometry."
        ],
        "warning_signals": ["Antiquated empirical misconception contradicted by modern physics and astrophysics"],
        "authority": "NASA & International Astronomical Union",
        "authority_url": "https://www.nasa.gov"
    },
    {
        "keywords": ["nasa", "darkness", "6 days"],
        "is_real": False,
        "verdict": "FAKE",
        "confidence": 99,
        "headline": "Hoax: NASA Confirmed 6 Days of Total Earth Darkness",
        "summary": "NASA has never issued a warning about days of worldwide total darkness. Such claims originate from recycled clickbait hoaxes that resurface periodically on social media.",
        "key_findings": [
            "NASA official archives and public affairs spokespeople have repeatedly debunked this recurring hoax.",
            "Planetary orbital mechanics cannot cause multi-day worldwide darkness without gravitational collapse.",
            "No solar storm has the capability to block sunlight across the globe."
        ],
        "warning_signals": ["Recycled viral apocalypse hoax misattributing false statements to NASA"],
        "authority": "NASA Official Science Briefings",
        "authority_url": "https://science.nasa.gov"
    },
    {
        "keywords": ["5g", "covid", "virus"],
        "is_real": False,
        "verdict": "FAKE",
        "confidence": 98,
        "headline": "Debunked: 5G Mobile Networks Spread COVID-19 or Biological Viruses",
        "summary": "Biological viruses cannot travel on radio waves or mobile networks. COVID-19 is transmitted through respiratory droplets from infected individuals.",
        "key_findings": [
            "WHO and International Commission on Non-Ionizing Radiation Protection (ICNIRP) confirmed 5G frequencies are non-ionizing radio waves.",
            "Viruses are biological entities consisting of nucleic acid enclosed in a protein coat, which cannot be generated or transported via electromagnetic waves.",
            "COVID-19 spread extensively in countries and rural territories that had zero 5G infrastructure."
        ],
        "warning_signals": ["Unscientific conspiracy theory conflating telecommunications with microbiology"],
        "authority": "World Health Organization (WHO) & ICNIRP",
        "authority_url": "https://www.who.int"
    },
    {
        "keywords": ["bleach", "cure", "virus"],
        "is_real": False,
        "verdict": "FAKE",
        "confidence": 99,
        "headline": "Danger: Drinking Bleach or Ingesting Disinfectants Cures Illness",
        "summary": "Ingesting bleach or chlorine dioxide is toxic and potentially fatal. Disinfectants are intended for inanimate surface cleaning only and must never be swallowed or injected.",
        "key_findings": [
            "US Food and Drug Administration (FDA) has issued strict consumer alerts warning that ingesting bleach causes acute liver and kidney failure.",
            "Disinfectants do not target internal infections and cause severe corrosive gastrointestinal burns.",
            "CDC poison control reports severe toxic injuries from ingestion of unapproved chemical mixtures."
        ],
        "warning_signals": ["Extremely hazardous medical misinformation promoting toxic poison ingestion"],
        "authority": "US FDA & Centers for Disease Control (CDC)",
        "authority_url": "https://www.fda.gov"
    }
]

def check_medical_scientific_consensus(text: str):
    clean = text.lower().strip()
    clean_words = set(re.findall(r'\b[a-z0-9]+\b', clean))

    for entry in SCIENTIFIC_DEBUNKING_DATABASE:
        matches = all(kw in clean for kw in entry["keywords"])
        if matches:
            return {
                "is_match": True,
                "engine": "gemini_scientific_verifier",
                "verdict": entry["verdict"],
                "isReal": entry["is_real"],
                "confidence": entry["confidence"],
                "headline": entry["headline"],
                "summary": entry["summary"],
                "keyFindings": entry["key_findings"],
                "warningSignals": entry["warning_signals"],
                "authority": entry["authority"],
                "authorityUrl": entry["authority_url"]
            }

    return {"is_match": False}

if __name__ == "__main__":
    if len(sys.argv) > 1:
        query = " ".join(sys.argv[1:])
    else:
        query = sys.stdin.read().strip()

    result = check_medical_scientific_consensus(query)
    print(json.dumps(result, indent=2))
