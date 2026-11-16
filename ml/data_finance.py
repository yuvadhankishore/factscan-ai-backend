#!/usr/bin/env python3
"""
FactScan AI - Financial, Banking & Economic Training Corpus
Monetary authorities and official economic data versus phishing scams and financial panic hoaxes.
"""

FINANCE_CORPUS = [
    # --- REAL NEWS (1) ---
    (
        "Reserve Bank of India Monetary Policy Committee keeps repo rate unchanged at 6.50 percent",
        "The Monetary Policy Committee voted unanimously to maintain the policy benchmark repo rate at 6.50 percent, maintaining a policy stance focused on withdrawal of accommodation to align consumer price inflation with the medium-term 4 percent target.",
        1
    ),
    (
        "Federal Reserve announces transition to updated real-time interbank settlement system FedNow",
        "The Federal Reserve Board officially launched the FedNow instant payment service, enabling depository institutions across the United States to process 24/7 clearing and settlement for retail customer transactions.",
        1
    ),
    (
        "International Monetary Fund raises global economic growth projection to 3.2 percent in World Economic Outlook",
        "The IMF released its multilateral surveillance assessment citing resilient private consumption in the United States and emerging markets, while urging fiscal consolidation to stabilize sovereign public debt ratios.",
        1
    ),
    (
        "Securities and Exchange Board of India mandates mandatory direct payout of securities to client demat accounts",
        "SEBI issued operational circulars directing clearing corporations to credit securities directly into investor demat accounts rather than broker settlement pools, enhancing investor asset protection.",
        1
    ),
    (
        "Ministry of Finance reports annual gross GST revenues exceeded twenty lakh crore rupees",
        "Official tax collection statistics published by the Department of Revenue showed a 13.4 percent year-on-year increase in aggregate goods and services tax collections, reflecting broader compliance and economic formalization.",
        1
    ),
    (
        "European Central Bank reduces deposit facility rate following sustained euro area disinflation",
        "The Governing Council of the European Central Bank decided to lower its key deposit facility rate by 25 basis points after headline harmonised inflation fell within its target corridor.",
        1
    ),

    # --- FAKE NEWS / DEBUNKED (0) ---
    (
        "RBI issues emergency notification invalidating all 500 currency notes from midnight tonight",
        "Sensational WhatsApp forwards allege the Reserve Bank ordered all 500 rupee bank notes demonetized and voided effective immediately. The Reserve Bank of India issued a public advisory confirming the notice is fake and all genuine notes remain legal tender.",
        0
    ),
    (
        "Government depositing 50,000 rupees into bank accounts of anyone who fills this online link",
        "Phishing scams impersonating government welfare programs claim direct treasury disbursements are being distributed to all mobile numbers clicking an unverified portal. Ministry of Finance cyber defense teams confirmed the link is phishing credential theft.",
        0
    ),
    (
        "Entering your ATM pin backwards sends silent distress signal to police dispatch immediately",
        "Social media safety tips claim entering a debit card PIN in reverse order dispenses cash while silently notifying emergency police units. Banking associations and ATM manufacturers confirm reverse PINs simply fail authentication without triggering emergency dispatch.",
        0
    ),
    (
        "Supreme Court orders all personal bank loans and credit card debts legally forgiven nationwide",
        "Conspiracy blog articles claim judicial decrees abolished all consumer debt obligations across commercial banks. Court registries and legal scholars verify that contractual banking liabilities remain fully enforceable under commercial debt recovery statutes.",
        0
    ),
    (
        "All bank accounts not linked to new cryptocurrency app will be permanently frozen by Friday",
        "Fraudulent SMS text messages urge bank customers to link their savings accounts to an unverified third-party cryptocurrency wallet to avoid account closure. Central banks warn financial institutions never require crypto links for account maintenance.",
        0
    ),
    (
        "Secret government automated trading algorithm guarantees 500 percent profit every week",
        "Online advertisements claim state financial regulators developed a guaranteed artificial intelligence trading bot for citizen wealth redistribution. Securities regulators have placed the fraudulent trading domains on unauthorized operator alert lists.",
        0
    )
]
