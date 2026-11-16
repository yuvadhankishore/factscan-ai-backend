#!/usr/bin/env python3
"""
FactScan AI - Geopolitical & International Affairs Training Corpus
Contains authenticated international journalistic reports and debunked geopolitical disinformation.
"""

GEOPOLITICAL_CORPUS = [
    # --- REAL NEWS (1) ---
    (
        "United Nations General Assembly adopts resolution on sustainable development and clean water access",
        "The UN General Assembly passed a comprehensive resolution with 162 member states voting in favor, reaffirming commitments to equitable global groundwater management and international infrastructure funding under the 2030 Sustainable Development Agenda.",
        1
    ),
    (
        "International Court of Justice issues advisory opinion on sovereign maritime boundary demarcation",
        "The International Court of Justice in The Hague delivered a binding advisory ruling clarifying international law guidelines for contiguous exclusive economic zones in accordance with the United Nations Convention on the Law of the Sea (UNCLOS).",
        1
    ),
    (
        "European Union and Japan sign bilateral digital trade and cross-border data agreement",
        "Trade representatives from the European Commission and the Government of Japan concluded a landmark commercial pact eliminating customs duties on electronic transmissions and establishing interoperable cryptographic trust services.",
        1
    ),
    (
        "Election Commission of India introduces verifiable paper audit trail across all parliamentary polling booths",
        "The Election Commission announced the universal implementation of VVPAT systems across all general election constituencies, enabling voters to visually inspect a printed slip verifying their cast electronic ballot.",
        1
    ),
    (
        "African Union admitted as permanent member of the G20 intergovernmental forum",
        "During the Leaders Summit, the Group of Twenty unanimously agreed to grant permanent member status to the African Union, representing 55 member states to strengthen Global South representation in multilateral economic discussions.",
        1
    ),
    (
        "ASEAN summit concludes with joint declaration on regional maritime security and disaster response",
        "Association of Southeast Asian Nations foreign ministers issued a joint communiqué pledging coordinated disaster relief deployments and standardized protocols for search and rescue operations in regional sea corridors.",
        1
    ),

    # --- FAKE NEWS / DEBUNKED (0) ---
    (
        "United Nations passes secret resolution dissolving all national armies by next month",
        "Viral social media posts assert that an emergency UN resolution was secretly signed transferring control of all sovereign military forces to a centralized global security command. Official UN voting records and international defense ministries confirm no such decree exists.",
        0
    ),
    (
        "Foreign embassy issues emergency notice that all tourist visas are permanently revoked effective tonight",
        "Panic chain messages circulate claiming diplomatic missions have canceled all issued tourist and employment visas without appeal. Consular affairs directorates clarified the warning is entirely fabricated by phishing fraud accounts.",
        0
    ),
    (
        "Government secretly signed treaty ceding national territory to foreign mining consortium",
        "Sensational conspiracy articles claim senior officials leased protected island territories to multinational companies without legislative oversight. Ministry of External Affairs and parliament archives confirm no such agreement was ever negotiated.",
        0
    ),
    (
        "Voting machines equipped with secret satellite antennas that flip ballots after 6 PM",
        "Disinformation forwards claim electronic voting machines connect to foreign orbital satellites to alter electoral tallies after polling stations close. Independent electoral commissions and accredited software engineers confirm EVMs are stand-alone, air-gapped devices without wireless hardware.",
        0
    ),
    (
        "United Nations declares global mandatory biometric passport starting tomorrow morning",
        "Forwarded warnings claim citizens will be detained at borders unless they carry a new global identification barcode issued by international agencies. International Civil Aviation Organization confirmed standard sovereign passport rules remain unchanged.",
        0
    ),
    (
        "Leaked diplomatic memo reveals neighboring nation declaring war on social media",
        "Fabricated screenshots styled after official diplomatic stationery allege active hostilities were declared over social networks. National defense ministries dismissed the forged graphic and initiated cyber forensic tracing.",
        0
    )
]
