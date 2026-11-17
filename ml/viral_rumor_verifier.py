#!/usr/bin/env python3
"""
FactScan AI - Political & Breaking Viral Rumor Debunking Module
Identifies documented viral social media hoaxes, fake government schemes, and fabricated quotes.
"""

import sys
import json
import re

VIRAL_RUMOR_DATABASE = [
    {
        "keywords": ["unesco", "national anthem", "best"],
        "is_real": False,
        "verdict": "FAKE",
        "confidence": 99,
        "headline": "Hoax: UNESCO Declared Indian National Anthem Best in the World",
        "summary": "UNESCO has never rated, ranked, or declared any national anthem as the 'best in the world'. UNESCO official representatives have confirmed this is a persistent viral hoax.",
        "key_findings": [
            "UNESCO stated in official press releases that it does not evaluate or award national anthems.",
            "This chain message has circulated on email and messaging apps since at least 2008 without any official foundation.",
            "Official UNESCO constitution contains no program for ranking cultural symbols of member states."
        ],
        "warning_signals": ["Recurrent viral forward misattributing non-existent awards to international bodies"],
        "authority": "UNESCO Official Spokesperson",
        "authority_url": "https://www.unesco.org"
    },
    {
        "keywords": ["gps", "chip", "note", "currency"],
        "is_real": False,
        "verdict": "FAKE",
        "confidence": 99,
        "headline": "Hoax: Currency Banknotes Contain Nano GPS Tracking Chips",
        "summary": "Banknotes do not contain nano GPS microchips or RFID tracking devices. The Reserve Bank and central banks globally have confirmed currency uses standard anti-counterfeit security threads.",
        "key_findings": [
            "Central banks confirm no banknotes anywhere in the world contain silicon microchips or GPS transponders.",
            "GPS tracking requires battery power, active antennae, and satellite line-of-sight impossible within paper banknotes.",
            "Central banks official specifications list watermarks, latent images, and security threads as anti-counterfeiting measures."
        ],
        "warning_signals": ["Technologically impossible conspiracy myth regarding banknote tracking"],
        "authority": "Reserve Bank & International Currency Association",
        "authority_url": "https://www.rbi.org.in"
    },
    {
        "keywords": ["whatsapp", "charge", "tomorrow", "blue"],
        "is_real": False,
        "verdict": "FAKE",
        "confidence": 99,
        "headline": "Hoax: WhatsApp Charging Monthly Fee Starting Tomorrow",
        "summary": "WhatsApp is not becoming a paid service and has never announced charges for standard personal messaging. This chain message is an old chain hoax that circulates every year.",
        "key_findings": [
            "Meta and WhatsApp have officially confirmed WhatsApp remains completely free for personal communication.",
            "Chain messages urging users to forward to 10 or 20 contacts to keep service active are classic spam chain letters.",
            "System updates or policy changes are posted directly through WhatsApp official blogs, not forwarded chat messages."
        ],
        "warning_signals": ["Classic viral chain hoax with urgent forwarding instructions"],
        "authority": "Meta Official Policy & WhatsApp Blog",
        "authority_url": "https://blog.whatsapp.com"
    },
    {
        "keywords": ["free", "smartphone", "government", "scheme"],
        "is_real": False,
        "verdict": "FAKE",
        "confidence": 98,
        "headline": "Scam: Government Giving Free Laptops or Smartphones to All Students",
        "summary": "Viral messages promising free laptops or smartphones to all students via third-party web links are phishing scams designed to harvest personal credentials and sensitive information.",
        "key_findings": [
            "PIB Fact Check and official ministries confirm no such nationwide universal gadget distribution scheme exists.",
            "Links in these viral messages lead to fraudulent phishing websites rather than official government domains (.gov.in or .gov).",
            "Genuine welfare programs require application through verified national portals without fee payments."
        ],
        "warning_signals": ["Malicious phishing forward imitating official government welfare initiatives"],
        "authority": "PIB Fact Check & National Cyber Crime Reporting Portal",
        "authority_url": "https://factcheck.pib.gov.in"
    },
    {
        "keywords": ["sim", "block", "24 hours", "kyc"],
        "is_real": False,
        "verdict": "FAKE",
        "confidence": 99,
        "headline": "Fraud: SIM Card Disconnection in 24 Hours Due to Pending KYC",
        "summary": "Telecom operators do not send SMS threats demanding immediate calls or app downloads to prevent 24-hour SIM deactivation. These are cyber phishing messages attempting account takeover.",
        "key_findings": [
            "Department of Telecommunications (DoT) and TRAI strictly prohibit unauthorized SIM deactivation threats via SMS.",
            "Messages asking users to call random mobile numbers or install remote access APKs are identity theft traps.",
            "Legitimate telecom KYC updates are processed exclusively at official retail stores or verified carrier apps."
        ],
        "warning_signals": ["High-risk telecom impersonation fraud inducing artificial urgency"],
        "authority": "TRAI & Department of Telecommunications",
        "authority_url": "https://www.trai.gov.in"
    }
]

def check_viral_rumors(text: str):
    clean = text.lower().strip()

    for entry in VIRAL_RUMOR_DATABASE:
        matches = all(kw in clean for kw in entry["keywords"])
        if matches:
            return {
                "is_match": True,
                "engine": "gemini_viral_rumor_verifier",
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

    result = check_viral_rumors(query)
    print(json.dumps(result, indent=2))
