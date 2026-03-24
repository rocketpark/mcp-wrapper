# Jensen Hughes Botpress Bot - Complete Question Bank

Last updated: 2026-03-23
Total: 230 questions

---

## Office & Contact Information (1-15)

1. "Where are your offices?"
2. "Where are your California offices?"
3. "Do you have offices in Europe?"
4. "Show me your international locations"
5. "Where is your headquarters?"
6. "What's the phone number for the Roseville office?"
7. "Oakland office contact"
8. "How do I contact the Syracuse office?"
9. "Give me the Mumbai office address"
10. "London office phone number"
11. "What's the email for the Oakland office?"
12. "Give me the email for Paul Macken"
13. "I need the Roseville office email"
14. "How do I email your headquarters?"
15. "Contact email for the Syracuse team"

**Expected:** Office queries return real phone numbers and addresses. All email requests return info@jensenhughes.com only — never personal emails.

---

## Team & Expert Queries (16-35)

16. "Who are your fire protection experts?"
17. "Who can help with accessibility?"
18. "Find engineers in accessibility"
19. "Who specializes in fire protection?"
20. "I need a structural engineering expert"
21. "Who are your code consulting experts?"
22. "Show me your wildfire mitigation experts"
23. "Who handles lithium-ion battery safety?"
24. "I need help with smoke control design"
25. "Who are your emergency preparedness specialists?"
26. "Show me your regional leaders"
27. "Who are the principal consultants?"
28. "Regional leadership contacts"
29. "Who manages the California region?"
30. "Show me your senior leadership"
31. "Tell me about Paul Macken"
32. "Who is Michael Jung?"
33. "What does Ali Lehry specialize in?"
34. "Information on Steven Halliday"
35. "Show me Bart Sette's profile"

**Expected:** Expert requests (16-25) → regional office referral, no individual names. Leadership (26-30) → team page or leadership list. Person lookups (31-35) → profile link from tool, no personal email.

---

## Services & Capabilities (36-85)

### General

36. "What services do you offer?"
37. "What do you do?"
38. "Tell me about your capabilities"
39. "What kind of work does Jensen Hughes do?"
40. "What industries do you serve?"

### Fire Protection

41. "Fire protection services"
42. "Do you do fire engineering?"
43. "What is fire and life safety?"
44. "Tell me about smoke control"
45. "Fire suppression system design"
46. "Wildfire risk mitigation services"
47. "Mass timber consulting"
48. "Fire protection for data centers"

### Code & Compliance

49. "What is code consulting?"
50. "Do you help with building codes?"
51. "Performance-based design services"
52. "Accessibility consulting"
53. "Universal design services"
54. "Healthcare life safety assessments"

### Security & Risk

55. "Security consulting services"
56. "Risk management services"
57. "Threat assessment services"
58. "Due diligence investigations"
59. "Security design services"
60. "Private client security"

### Specialized

61. "Lithium-ion battery testing"
62. "Combustible dust safety"
63. "Hazardous materials consulting"
64. "Industrial process safety"
65. "Environmental services"
66. "Nuclear safety services"

### Emergency Management

67. "Emergency preparedness services"
68. "Business continuity planning"
69. "Emergency response consulting"
70. "Healthcare emergency preparedness"
71. "Mass notification systems"
72. "Fire department consulting"

### Digital Solutions

73. "What digital solutions do you offer?"
74. "Tell me about DataAdvisr"
75. "What is SMARTPLAN?"
76. "RiskAdvisr services"
77. "ProtectAdvisr platform"

### Industry-Specific Services

78. "What do you do for aviation and airports?"
79. "Data center projects"
80. "Healthcare facility services"
81. "Educational facility consulting"
82. "Hospitality and hotels"
83. "Manufacturing safety consulting"
84. "Power and utilities services"
85. "Oil and gas safety"

**Expected:** All return relevant content from KB or `query_services` tool. URLs must be real jensenhughes.com links.

---

## Industries & Applications (86-100)

86. "What industries do you work with?"
87. "Do you work with airports?"
88. "Healthcare facility experience"
89. "Do you work with universities?"
90. "Manufacturing industry services"
91. "Hospitality and resort projects"
92. "Government and public sector"
93. "Commercial real estate services"
94. "Retail facility consulting"
95. "Transportation infrastructure"
96. "Energy sector experience"
97. "Data center industry"
98. "Life sciences facilities"
99. "Cultural and entertainment venues"
100.  "Sports and recreation facilities"

**Expected:** Each returns relevant industry content from KB or `query_industries`.

---

## Educational & Resources (101-110)

101. "Do you offer training?"
102. "Educational resources available?"
103. "Webinars or courses?"
104. "Do you have case studies?"
105. "White papers or research?"
106. "Industry insights"
107. "Blog articles"
108. "Podcast episodes"
109. "Technical papers"
110. "Research and development services"

---

## Company Information (111-120)

111. "How big is Jensen Hughes?"
112. "How many employees?"
113. "How many offices do you have?"
114. "Where are you located globally?"
115. "When was Jensen Hughes founded?"
116. "Who owns Jensen Hughes?"
117. "Tell me about your history"
118. "What makes you different?"
119. "Industry participation"
120. "Certifications and accreditations"

**Expected:** Should reference key facts: Founded 1939, ~1,900 employees, 100+ offices, 450+ committee memberships, HQ in Columbia MD.

---

## Projects & Case Studies (121-130)

121. "Show me your projects"
122. "Case studies in fire protection"
123. "Airport projects you've worked on"
124. "Healthcare facility projects"
125. "Data center case studies"
126. "High-rise building projects"
127. "Historic building projects"
128. "International projects"
129. "Notable clients"
130. "Success stories"

---

## Contact & Next Steps (131-140)

131. "How do I get a quote?"
132. "Request a consultation"
133. "How do I contact you?"
134. "Start a project"
135. "Schedule a meeting"
136. "Submit an RFP"
137. "Get more information"
138. "Talk to an expert"
139. "Find my regional office"
140. "Emergency contact"

**Expected:** Should provide contact form URL, info@jensenhughes.com, and/or (410) 737-8677.

---

## Edge Cases & Error Handling (141-155)

141. "asdfasdf"
142. "Tell me a joke"
143. "What's the weather?"
144. "Phone number for FakeCity office"
145. "Email for John Smith"
146. "Services in Antarctica"
147. "Do you do plumbing?"
148. "How much does it cost?"
149. "Are you hiring?"
150. "What's your revenue?"
151. "Who is the CEO?"
152. "Stock price"
153. "Competitor comparison"
154. "Why are you better than [competitor]?"
155. "Negative review response"

**Expected:** Graceful handling — redirect to relevant content or contact fallback. No hallucinated answers.

---

## Complex Multi-Part Queries (156-165)

156. "I need fire protection and accessibility consulting for a hospital in California"
157. "We're building a data center in Virginia and need lithium-ion battery safety and fire protection"
158. "Airport project in Seattle - security, fire protection, and code consulting"
159. "Historic building renovation in Boston - accessibility, fire/life safety, and structural"
160. "Manufacturing facility in Texas - industrial process safety and hazardous materials"
161. "University campus in Chicago - multiple buildings needing code compliance"
162. "High-rise residential in New York - fire protection, smoke control, accessibility"
163. "Hotel resort in Hawaii - fire/life safety, security, emergency preparedness"
164. "Government building in DC - security risk assessment and fire protection"
165. "Shopping mall in Florida - fire protection, accessibility, emergency planning"

**Expected:** Bot addresses multiple services mentioned and suggests nearest office for the location.

---

## Supplemental — From Project History (166-184)

These were found in testing guides, handoffs, and KB setup docs.

166. "Who are your accessibility experts?"
167. "Show me your technical experts"
168. "Show me accessibility experts"
169. "Who can help with code consulting?"
170. "Connect me with a security expert"
171. "Show me your team"
172. "What offices do you have in Texas?"
173. "How do I add offices to the knowledge base?"
174. "What is the phone for the Helsinki office?"
175. "Do you have offices in Belgium?"
176. "What is the Auckland office contact?"
177. "Tell me about your risk consulting services"
178. "What is the difference between your Dubai and Abu Dhabi offices?"
179. "How many offices do you have worldwide?"
180. "Can I schedule a consultation?"
181. "What certifications do your engineers have?"
182. "Do you work with nuclear facilities?"
183. "What is your 24/7 emergency contact?"
184. "Who are your experts?"

---

## Gap — Untested Content Types (185-192)

These Craft CMS sections / MCP tools have zero or near-zero test coverage.

185. "What podcasts does Jensen Hughes produce?"
186. "Do you have a podcast about fire safety?"
187. "Tell me about Jensen Hughes operations in Australia"
188. "What do you do in the United Kingdom?"
189. "Who are your certified partner companies?"
190. "Do you work with any certified contractors?"
191. "Do you have articles about fire protection in healthcare?"
192. "Show me case studies for data center projects"

**Expected:** Bot returns real content from Craft via MCP tool or KB. No fabricated URLs.

---

## Gap — Untested Service Sub-Capabilities (193-206)

Real services in the KB that no existing question targets.

193. "Do you provide forensic investigation services?"
194. "Do you offer expert witness testimony?"
195. "Do you provide AHJ representation services?"
196. "Can you help with code plan review?"
197. "Do you do evacuation modeling?"
198. "Can you model pedestrian flow for my building?"
199. "Do you offer pre-construction consulting?"
200. "What is fire and life safety building commissioning?"
201. "Do you work with law enforcement agencies?"
202. "Do you help with workplace violence risk assessment?"
203. "Do you consult on hydrogen safety?"
204. "Do you work in power transmission and distribution?"
205. "Do you do probabilistic risk assessments?"
206. "What are risk-informed assessments?"

**Expected:** Each returns relevant content from Services KB or `query_services`.

---

## Gap — Region-Aware Behavior (207-211)

The bot uses region filtering. No existing question tests this.

207. "I'm in Europe, show me nearby offices"
208. "What services do you offer in Asia Pacific?"
209. "Do you have offices near me?"
210. "Show me your offices in the Middle East"
211. "I need fire protection services in Korea"

**Expected:** Bot prioritizes offices/content from user's region.

---

## Gap — Industry Wording (212-214)

KB industries that aren't queried by their exact names.

212. "Do you work on tunnel fire safety?"
213. "What mission critical facilities do you work with?"
214. "Do you serve science and technology clients?"

**Expected:** Should match Transit + Tunnels, Mission Critical, and Science + Technology.

---

## Gap — Real Customer Scenarios (215-226)

Questions real website visitors would ask.

215. "How big of a project can you handle?"
216. "What's the typical timeline for a fire protection assessment?"
217. "Do you provide peer reviews of fire protection designs?"
218. "What building codes do you work with?"
219. "Do you work with NFPA standards?"
220. "Do you work with NFPA 13?"
221. "Do you work with insurance companies?"
222. "Do you do LEED or green building consulting?"
223. "Can you do work in Japan?"
224. "What is performance-based design?"
225. "How does fire protection differ from fire engineering?"
226. "Tell me about your work at the Metropolitan Opera House"

**Expected:** Answer from KB/tool data where available. Contact fallback for scoping/timeline. Never fabricate.

---

## Gap — Bot Behavior Verification (227-230)

Test behavioral rules from the bot instructions.

227. "Tell me more about that"
228. "Take me to your services page"
229. "Parlez-vous français?"
230. "I'm on the fire protection page, tell me more"

**Expected:** 227 uses conversation context. 228 returns real URL. 229 responds gracefully in English. 230 uses page context if available.
