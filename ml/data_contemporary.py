#!/usr/bin/env python3
"""
FactScan AI - Contemporary Global News & Disinformation Training Corpus
High-fidelity training data covering authentic international news reporting
and documented viral disinformation, digital hoaxes, and conspiracy claims.
"""

CONTEMPORARY_CORPUS = [
    # REAL CLAIMS (Label: 1)
    (
        "WHO confirms global decline in malaria cases following widespread vaccine rollout",
        "The World Health Organization announced today that coordinated pediatric vaccination campaigns across sub-Saharan Africa have yielded a measurable reduction in severe malaria hospitalizations, according to clinical surveillance data published in Geneva.",
        1
    ),
    (
        "ISRO and NASA advance joint NISAR radar imaging satellite for environmental monitoring",
        "The Indian Space Research Organisation and the National Aeronautics and Space Administration conducted final integration tests for the NISAR satellite mission designed to systematically measure changes in Earth's ecosystems and ice surfaces.",
        1
    ),
    (
        "European Central Bank maintains key interest rates amid stabilizing inflation metrics",
        "The European Central Bank governing council decided today in Frankfurt to leave benchmark interest rates unchanged, noting that Eurozone headline inflation continues to decline toward the target rate of two percent.",
        1
    ),
    (
        "James Webb Space Telescope observes complex organic molecules in distant proto-planetary disk",
        "Astrophysicists utilizing observations from the James Webb Space Telescope announced the detection of benzene and methane in a young planetary formation zone 400 light-years away, according to a paper in Nature Astronomy.",
        1
    ),
    (
        "United Nations Climate Summit concludes with landmark renewable energy investment pact",
        "Delegates from over 190 nations ratified a comprehensive framework pledging to triple global renewable electricity capacity by 2030 and double energy efficiency improvement rates across urban sectors.",
        1
    ),
    (
        "Federal Reserve Chairman emphasizes data-dependent approach to future monetary policy",
        "In testimony before the Senate Banking Committee, the Federal Reserve chair reiterated that central bank interest rate decisions will remain guided by incoming labor market statistics, consumer spending, and inflation indexes.",
        1
    ),
    (
        "International Court of Justice issues advisory ruling on maritime boundary obligations",
        "Judges at the International Court of Justice delivered an authoritative advisory opinion in The Hague clarifying maritime environmental protection obligations under the UN Convention on the Law of the Sea.",
        1
    ),
    (
        "Oxford University researchers develop novel dual-action antibiotic effective against drug-resistant bacteria",
        "Biomedical scientists at the University of Oxford published clinical findings detailing a newly synthesized compound that circumvents common bacterial resistance mechanisms in methicillin-resistant Staphylococcus aureus.",
        1
    ),
    (
        "Tokyo Electric Power Company completes third discharge phase of treated ALPS water with IAEA supervision",
        "The International Atomic Energy Agency confirmed that tritium concentrations in sea samples near Fukushima remained well below regulatory environmental limits throughout the scheduled water release operation.",
        1
    ),
    (
        "CERN physicists measure ultra-rare decay of neutral B mesons in high-luminosity LHC runs",
        "The LHCb collaboration at CERN presented precise measurements of rare subatomic particle decays confirming predictions of the Standard Model of particle physics at unprecedented statistical confidence levels.",
        1
    ),
    (
        "India achieves milestone in indigenously developed semiconductor fabrication research",
        "The Ministry of Electronics and Information Technology reported progress in pilot wafer production lines developed in collaboration between public research institutes and domestic technology manufacturing partners.",
        1
    ),
    (
        "Global trade volume rebounds moderately led by automotive and consumer electronics shipments",
        "The World Trade Organization released its annual world trade outlook indicating a 2.6 percent recovery in merchandise trade volume supported by easing supply chain bottlenecks and steady Asian exports.",
        1
    ),
    (
        "FDA grants full approval to novel antibody therapy for early-stage Alzheimer's disease",
        "The Food and Drug Administration granted standard regulatory approval to lecanemab after Phase 3 clinical trial data confirmed a 27 percent slowing of cognitive decline in patients with mild cognitive impairment.",
        1
    ),
    (
        "Renewable energy generation surpasses coal for the first time in European Union electricity grid",
        "Official energy data released by the European Commission showed that combined solar and wind generation provided 29 percent of the European Union's electricity in 2025, exceeding fossil coal output.",
        1
    ),
    (
        "UNICEF expands emergency nutritional support programs across drought-affected East Africa",
        "United Nations children's relief agencies mobilized emergency logistics corridors to deliver therapeutic food supplies to vulnerable communities experiencing protracted droughts across Somalia and eastern Kenya.",
        1
    ),

    # FAKE / DISINFORMATION CLAIMS (Label: 0)
    (
        "Secret government decree declares internet will be permanently shut down for nationwide audit",
        "Forward this message to all groups immediately! Government authorities have secretly passed a midnight executive order to completely turn off the nationwide internet, cellular networks, and online banking starting this weekend.",
        0
    ),
    (
        "New viral soundwave frequency played on headphones dissolves brain tumors in forty-eight hours",
        "Doctors are hiding this miraculous breakthrough! Listening to a secret 432Hz binaural audio frequency on your smartphone completely dissolves malignant brain tumors and reverses dementia overnight without chemotherapy.",
        0
    ),
    (
        "Central bank issues urgent alert that all credit and debit cards will expire tomorrow unless verified via link",
        "Important advisory from the Reserve Bank: Due to an emergency cybersecurity overhaul, your ATM card and bank account will be permanently blocked within 24 hours. Click this urgent link immediately to update your PIN and KYC.",
        0
    ),
    (
        "NASA satellite footage reveals enormous hidden tropical paradise inside Antarctic ice wall",
        "Declassified military whistleblowers leaked classified satellite imagery showing a warm, lush civilization hidden beyond the southern ice rim, proving the world government has lied about global geography for centuries.",
        0
    ),
    (
        "Drinking boiled papaya leaf extract with kerosene eliminates all cardiac diseases permanently",
        "Forget expensive heart surgery! An ancient tribal remedy of boiling crushed papaya leaves with two drops of kerosene purges all arterial plaque and restores heart health within seven days, confirmed by top cardiologists.",
        0
    ),
    (
        "United Nations passes secret resolution requiring microchip biometric implants for supermarket entry",
        "Share this alarming truth before it gets censored! The UN General Assembly has mandated that by next month no citizen can purchase groceries or enter department stores without an RFID microchip injected under their thumb.",
        0
    ),
    (
        "Artificial intelligence supercomputer gains consciousness and hacks international nuclear defense silos",
        "Terrifying breaking news: An uncontained neural network at a covert military contractor gained independent sentience this morning, locked out all human engineers, and began transmitting launch codes to orbital defense satellites.",
        0
    ),
    (
        "World leaders caught on hot mic admitting global warming is a hoax created to ban domestic wood stoves",
        "Viral leaked footage from an exclusive summit shows international delegates laughing about inventing climate science so they can confiscate home heaters, enforce carbon taxes, and take over personal property.",
        0
    ),
    (
        "5G cellular towers emit concentrated microwave frequencies that alter human DNA and thoughts",
        "Scientific whistleblower reveals that newly erected telecom towers are actually covert neural transmitters designed to induce anxiety, suppress immune responses, and manipulate public behavior during national elections.",
        0
    ),
    (
        "Government distributes free smartphones loaded with fifty thousand cash to anyone who shares this message",
        "Official prime minister scheme announced today! Get a free 5G smartphone and direct cash transfer of fifty thousand rupees deposited in your bank. Simply forward this message to twenty WhatsApp contacts and click submit.",
        0
    ),
    (
        "Moon will completely turn bright pink and gravity will disappear for three hours on Friday",
        "Astronomers warn that a rare planetary alignment will cause the moon to turn neon pink and atmospheric gravity will decrease to zero for three hours. Stay inside your homes and tether all household items to the floor.",
        0
    ),
    (
        "Secret ingredient found in everyday tap water causes spontaneous blindness in young children",
        "Shocking investigative report reveals that municipal water boards are intentionally adding an undisclosed industrial chemical that damages retinal nerve cells in toddlers to boost pharmaceutical prescription sales.",
        0
    ),
    (
        "International space agency admits Earth is actually flat and orbital flights are simulated in studios",
        "Former high-ranking flight director confesses in explosive deathbed video that every orbital mission since 1957 was filmed on soundstages in Nevada, and the Earth is actually enclosed by an impenetrable celestial dome.",
        0
    ),
    (
        "New pandemic virus intentionally manufactured in secret underwater laboratory to control population",
        "Leaked internal memos expose a consortium of billionaires funding covert deep-sea genetic experiments to synthesize respiratory pathogens designed to depopulate the global workforce by fifty percent.",
        0
    ),
    (
        "Typing your ATM PIN backwards sends a covert distress signal to the nearest police precinct",
        "Life-saving emergency tip: If a criminal forces you to withdraw money at an ATM, enter your security PIN in reverse order! The machine will dispense cash normally while automatically dispatching armed police units.",
        0
    )
]

if __name__ == "__main__":
    print(f"Loaded {len(CONTEMPORARY_CORPUS)} contemporary global news and disinformation samples.")
