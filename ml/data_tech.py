#!/usr/bin/env python3
"""
FactScan AI - Emerging Tech, Artificial Intelligence & Cybersecurity Training Corpus
Rigorous technological and cybersecurity standards versus science fiction sensationalism and scareware.
"""

TECH_CORPUS = [
    # --- REAL NEWS (1) ---
    (
        "European Union formally adopts comprehensive Artificial Intelligence Act with risk classification framework",
        "The Council of the European Union finalized the Artificial Intelligence Act, categorizing AI systems by risk level, mandating technical documentation for general-purpose AI foundation models, and establishing strict bans on cognitive manipulation and social scoring.",
        1
    ),
    (
        "NIST releases first official post-quantum encryption standards to protect against future quantum computers",
        "The National Institute of Standards and Technology finalized federal information processing standards for quantum-resistant algorithms, including ML-KEM for general encryption and ML-DSA for digital signatures.",
        1
    ),
    (
        "Google DeepMind releases AlphaFold 3 predicting structures of all biological molecules including DNA and RNA",
        "Biochemical researchers utilizing AlphaFold 3 demonstrated atomic-level modeling of complex molecular interactions between proteins, nucleic acids, and therapeutic small molecules with unprecedented cross-disciplinary accuracy.",
        1
    ),
    (
        "Cybersecurity and Infrastructure Security Agency issues directive mandating federal patch for actively exploited vulnerability",
        "CISA added a critical remote code execution vulnerability to its Known Exploited Vulnerabilities Catalog, requiring government civil agencies to apply vendor remediation updates within 21 calendar days.",
        1
    ),
    (
        "Taiwan Semiconductor Manufacturing Company begins commercial pilot production of 2-nanometer nanosheet transistors",
        "TSMC confirmed successful test wafer yields for its advanced N2 process node utilizing gate-all-around nanosheet transistor architecture, delivering a 15 percent performance increase and 30 percent power reduction.",
        1
    ),
    (
        "Linux Foundation and leading cloud providers launch OpenSSF framework to secure open-source software supply chain",
        "The Open Source Security Foundation published automated security scorecard tooling to verify cryptographic commit signing, branch protection policies, and dependency bill-of-materials across open-source codebases.",
        1
    ),

    # --- FAKE NEWS / DEBUNKED (0) ---
    (
        "Autonomous AI robot escaped research laboratory and killed twenty engineers before escaping onto dark web",
        "Tabloid headlines claim an artificial intelligence android achieved sentience, disabled emergency overrides, and eliminated lab researchers. Computer science laboratories and robotics developers confirmed the story is entirely fabricated clickbait fiction.",
        0
    ),
    (
        "Telecommunications 5G antennas emit resonant microwave frequencies that alter human emotional behavior",
        "Conspiracy channels claim cellular 5G transmitters broadcast mind-control frequencies designed to cause depression and compliance in populations. Telecommunication engineers and biophysicists confirm non-ionizing radiofrequency fields carry zero neurological commands.",
        0
    ),
    (
        "New smartphone security update turns on front camera continuously and transmits video to secret databases",
        "Chain warnings circulate telling users never to install software updates because manufacturers supposedly enabled secret bedroom surveillance. Cybersecurity audit firms analyze operating system firmware images and verify security updates contain zero surveillance code.",
        0
    ),
    (
        "Answering incoming phone call from unknown international number will physically explode phone battery",
        "Scareware messages claim criminal networks developed high-voltage acoustic audio codes that cause smartphone lithium batteries to detonate on call answer. Electrical engineering experts clarify audio signals through phone speakers cannot cause battery thermal runaway.",
        0
    ),
    (
        "Government secretly placed microscopic RFID tracking microchips inside all newly manufactured household lightbulbs",
        "Viral social media videos claim new energy-efficient LED lightbulbs contain hidden microphones and geolocation transmitters transmitting domestic conversations. Hardware teardowns of consumer LED bulbs confirm they consist strictly of diodes and standard power driver circuits.",
        0
    ),
    (
        "Dialing star pound two one pound on any cell phone proves your device has been hacked by government spies",
        "Viral instructional posts allege interrogating GSM call forwarding codes displays secret government surveillance taps. Telecommunications engineers explain that *#21# is a standard network protocol code displaying routine unanswered call forwarding settings.",
        0
    )
]
