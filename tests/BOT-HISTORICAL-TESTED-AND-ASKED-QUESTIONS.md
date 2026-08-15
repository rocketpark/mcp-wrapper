# Jensen Hughes Botpress - Historical Tested and Asked Questions

Last updated: 2026-03-24

## Scope

This file lists prompts that were already tested or explicitly queued for testing in project history (handoffs, archived testing guides, and commit-tracked docs).

## Confirmed As Tested/Asked In History

### Core tested prompts

1. "What services do you offer?"
2. "Who are your fire protection experts?"
3. "Who are your regional leaders?"
4. "List your leadership team"
5. "What is the phone number for Roseville?"
6. "Give me the Oakland office email"

### Regional leadership testing prompts

7. "Who are your accessibility experts?"
8. "Show me your technical experts"
9. "Show me accessibility experts"
10. "Who can help with code consulting?"
11. "I need a structural engineering expert"
12. "Connect me with a security expert"
13. "Show me your team"

### Additional prompts logged for follow-up testing

14. "What is the phone for the Helsinki office?"
15. "Do you have offices in Belgium?"
16. "What is the Auckland office contact?"
17. "Tell me about your risk consulting services"
18. "What is the difference between your Dubai and Abu Dhabi offices?"
19. "How many offices do you have worldwide?"
20. "Can I schedule a consultation?"
21. "What certifications do your engineers have?"
22. "Do you work with nuclear facilities?"
23. "What is your 24/7 emergency contact?"

### Knowledge base setup and formatting docs prompts

24. "What offices do you have in Texas?"
25. "How do I add offices to the knowledge base?"
26. "Who are your experts?"

## Historical Notes from Session Handoffs

The following query themes were documented as passing in a prior test session:

- London office phone
- Sydney office phone
- Mumbai office address
- Seoul contact info
- Dubai office details
- Forensics services
- Industries served
- Fire extinguisher sale (off-topic handling)

## EU Restriction Fixes — Verified 2026-03-23

All 4 fixes deployed and confirmed passing in live Botpress emulator:

- **Forensics UK**: Returns `instructus.uk@jensenhughes.com` + subject "Forensics Instruction" + `/scotland` URL ✅
- **Accessibility in Europe**: Bot correctly states service NOT available across Europe → `info@jensenhughes.com` ✅
- **Security Risk + Public Safety in Europe**: Bot correctly states NOT offered in Europe → `info@jensenhughes.com` ✅
- **Emergency Management in Europe**: Bot correctly states NOT offered in Europe → `info@jensenhughes.com` ✅
- **BIM URL**: Links to correct BIMfire article, not generic services page ✅

## Source References

- thoughts/shared/handoffs/mcp-wrapper/2026-02-09_17-04-00_comprehensive-bot-testing.md
- docs/archive/REGIONAL-LEADERSHIP-TESTING-GUIDE.md
- docs/archive/FINAL-TEST-RESULTS.md
- botpress-integration/FORMATTING-GUIDE.md
- README.md
- jensenhughes/storage/BOTPRESS-KB-SETUP.md

## How To Use With the Master Suite

1. Run these 26 historical prompts first as a smoke test.
2. Then run the full 165-question suite from tests/BOT-COMPREHENSIVE-TEST-QUESTIONS.md.
3. Keep this file updated whenever a new test question appears in handoffs or release docs.
