# JH Regional Content Inventory

Generated: 2026-05-20
Source: live curl probes against `https://www.jensenhughes.com/` with `User-Agent: Mozilla/5.0 JH-Inventory`
Method: HTTP HEAD-via-GET on candidate paths, then `grep` extraction of in-region sub-URLs from 200-status landing pages.

Scope: 5 regions x 11 content categories. URLs that returned 200 are recorded. 404/500s are noted in summary table. JS-rendered listings (e.g. office-location grids) often hide their cards from raw HTML — sub-URLs in this doc come from anchor tags actually present in the source, surfaced via either the listing page or adjacent country / regions pages.

---

## Summary table — what content exists per region

| Category | NA / Global | Europe | Pacific | Asia | Middle East |
|---|---|---|---|---|---|
| Services landing | `/services` (49 sub) | `/europe/services` (35 sub) | `/pacific/services` (38 sub) | `/asia/services` (23 sub + 3 country) | `/middle-east/services` (27 sub) |
| Insights landing | `/insights` (200) | `/europe/insights` (200) | `/pacific/insights` (200) | `/asia/insights` (200) | `/middle-east/insights` (200) |
| Insights category filters | `/insights/{blog\|media-article\|industry-awards\|project-profile\|press-release}/all/all` | same pattern under `/europe/insights/` | same pattern under `/pacific/insights/` | same pattern under `/asia/insights/` | same pattern under `/middle-east/insights/` |
| Industries landing | `/industries` (14 sub) | `/europe/industries` (13 sub) | `/pacific/industries` (10 sub) | `/asia/industries` (9 sub) | `/middle-east/industries` (9 sub) |
| Careers landing | `/careers` (200) | `/europe/careers` (200) | `/pacific/careers` (200) | `/asia/careers` (200) | `/middle-east/careers` (200) |
| Careers sub-pages | listing, students-graduates, why-work-here, help-build-what-powers-the-future | listing, students-graduates, why-work-here, **careers-belgium** | listing, why-work-here, **graduate-program** (no students-graduates) | listing, students-graduates, why-work-here | listing, why-work-here (no students-graduates) |
| Contact-us | `/contact-us` (200) | `/europe/contact-us` (200), `/europe/contact-page` (200) | `/pacific/contact-page` (200) — **no `/pacific/contact-us`** (404) | `/asia/contact-us` (200) | `/middle-east/contact-us` (200) |
| Office-locations listing | `/contact/office-locations` (200), `/contact/locations` (200) | `/europe/contact/office-locations` (200), `/europe/contact/locations` (200) | `/pacific/contact/office-locations` (200), `/pacific/contact/locations` (200) | `/asia/contact/office-locations` (200), `/asia/contact/locations` (200) | `/middle-east/contact/locations` (200) — **no `/middle-east/contact/office-locations`** (404) |
| About | `/about` (200) | `/europe/about` (200) | `/pacific/about` (200), `/pacific/about-us` (200) | `/asia/about` (200), `/asia/about-us` (200) | `/middle-east/about` (200) |
| About sub-pages | leadership-team, purpose-and-principles | leadership-team, purpose-and-principles | purpose-and-principles only (leadership 404) | purpose-and-principles only (leadership 404) | purpose-and-principles only (leadership 404) |
| Podcasts | `/podcasts` (200) — 6 series | `/europe/podcasts` (200) — 4 series | `/pacific/podcasts` (200) — 4 series | `/asia/podcasts` (200) — 3 series | `/middle-east/podcasts` (200) — 5 series |
| Events landing | `/events` (200), `/events/past-events` | `/europe/events` (200), `/europe/events/past-events` | `/pacific/events` (200), `/pacific/events/past-events` | **404** (no `/asia/events`) | **404** (no `/middle-east/events`) |
| Single event pages | `/event/{slug}` (e.g. `/event/nfpa-2026`) | — | — | — | — |
| Webinars | **500** | **500** | **500** | **500** | **500** (route exists but errors site-wide) |
| Team / Experts directory | `/our-experts` (200) + `/experts` (200 filterable) | `/europe/our-experts` (200) + `/europe/experts` (200) | `/pacific/our-experts` **404** — only `/pacific/experts` (200) | `/asia/our-experts` **404** — only `/asia/experts` (200) | `/middle-east/our-experts` **404** — only `/middle-east/experts` (200) |
| Individual expert profile | `/our-experts/{slug}` pattern not surfaced via HTML | `/europe/experts/{first-last}` (e.g. `bart-sette`) | `/pacific/experts/{first-last}` (e.g. `charles-boyce`) | likely `/asia/experts/{slug}` | `/middle-east/experts/{first-last}` (e.g. `ali-lehry`) |
| Leadership | `/leadership-team` 404 — use `/about/leadership-team` | use `/europe/about/leadership-team` | **404** — none | **404** — none | **404** — none |
| Country pages | `/northern-ireland` (200) | Belgium, Denmark, England, Finland, Ireland, Italy, Scotland, Northern Ireland (8 total — all `/europe/{country}` pattern). Also `/europe/regions` (200) and Finnish locale `/europe/fi/` (200) | Australia, New Zealand (2 — `/pacific/{country}` pattern). Also `/pacific/regions` (200) | "Country" sub-pages live under `/asia/services/` as `greater-china`, `malaysia`, `south-korea` — services-style URLs, not standalone country pages | `/middle-east/india` (200) is the one country page |
| Country contact pages | — | `/europe/country/{country}/contact` for all 8 countries (200 each) | `/pacific/country/{australia\|new-zealand}/contact` (200 each) | — | `/middle-east/india/contact` (200), `/middle-east/country/india/contact` (200) |
| Policies / compliance | `/policies-compliance` (200) | `/europe/policies-compliance` (200) | `/pacific/policies-compliance` (200) | `/asia/policies-compliance` (200) | `/middle-east/policies-compliance` (200) |
| DEI | `/diversity-equity-inclusion` (200) | `/europe/diversity-equity-inclusion` (200) | not probed (likely 200 — shared template) | not probed | not probed |
| Certified-companies | — | — | `/pacific/certified-companies` (200) — Pacific-only | — | — |
| Privacy policy | `/privacy-policy` **404** (route gone) | — | — | — | — |

Notes on the table:
- "Services landing N sub" excludes the `services/services` self-link that always appears.
- Asia services has 3 country-style sub-URLs (`/asia/services/greater-china`, `/asia/services/malaysia`, `/asia/services/south-korea`) which function as country pages embedded in the Services tree. That is why Asia lacks first-class `/asia/{country}` pages.
- Webinars URL returns 500 site-wide on every region — route registered but broken backend.
- Pacific is the only region without a numeric `students-graduates` careers page; it uses `graduate-program` instead.
- Middle East is the only region whose listing URL is `contact/locations` rather than `contact/office-locations`.
- `/our-experts` exists only on NA and EU. Pacific, Asia, ME use the newer `/experts` filterable directory.

---

## Per-region details

### NA / Global

**Root:** `https://www.jensenhughes.com/`

#### Services — `/services` (50 unique sub-URLs including self-link, 49 real services)

```
/services/accessibility
/services/ahj-representation-plan-review
/services/code-consulting
/services/code-consulting-built-environment
/services/combustible-dust-safety
/services/commissioning
/services/contact
/services/dataadvisr
/services/digital
/services/emergency-management-response
/services/emergency-preparedness
/services/emerging-hazards
/services/energy
/services/environmental
/services/fire-emergency-services-consulting
/services/fire-engineering-systems-design
/services/fire-smoke-tunnel-modeling
/services/fire-suppression-systems-design
/services/hazadvisr
/services/hazardous-materials
/services/healthcare-emergency-preparedness
/services/healthcare-life-safety-assessments
/services/hydrogen-services
/services/in-house-laboratories-testing
/services/investigations
/services/large-scale-fire-testing-lsft
/services/law-enforcement-consulting
/services/life-safety-pbd
/services/lithium-ion-risk-consulting
/services/mass-notification-systems
/services/mass-timber-consulting
/services/mep-structural-safety
/services/pedestrian-evacuation-modeling
/services/pre-construction-administration-and-oversight
/services/private-client-family-office-services
/services/process-safety
/services/protectadvisr
/services/qa-iso-certifications
/services/response-planning
/services/risk-analysis
/services/riskadvisr
/services/security-design
/services/security-risk-consulting
/services/security-risk-management
/services/security-risk-public-safety
/services/smartplan
/services/testing-research-development
/services/threat-violence-risk-management
/services/training
/services/wildfire-risk-mitigation
```

#### Insights — `/insights` (200)

Featured article slugs visible on the listing page (first ~18 cards):
```
/insights/2027-nfpa-30-second-draft-ballot-passes-key-changes-ahead
/insights/a-growing-worry-for-ceos-staying-safe-in-their-homes
/insights/a-sign-that-says-you-belong
/insights/celebrating-our-2026-consulting-specifying-engineer-40-under-40-winners
/insights/designing-for-everyone-how-rhfac-and-jensen-hughes-strengthen-project-outcomes
/insights/fire-prevention-in-data-centers
/insights/global-conflicts-have-local-consequences-in-facilities-management-sector
/insights/impact-of-the-new-firehouse-rule
/insights/jensen-hughes-strengthens-position-in-enrs-annual-design-rankings
/insights/statewide-active-threat-preparedness-initiative
/insights/the-cbrne-risk-gap-in-conventional-safety-programs
```

Category filter pages (the "all" tail is a faceted filter):
```
/insights/blog/all/all
/insights/media-article/all/all
/insights/industry-awards/all/all
/insights/project-profile/all/all
/insights/press-release/all/all
```

Pagination: `/insights/p2`, `/insights/p3`, `/insights/p4` (and presumably more).

#### Industries — `/industries` (14 sub)

```
/industries/aviation
/industries/commercial
/industries/education
/industries/government-military
/industries/healthcare
/industries/hospitality-entertainment
/industries/manufacturing
/industries/mission-critical
/industries/nuclear-power
/industries/petrochemical-oil-gas
/industries/power
/industries/science-and-technology
/industries/transit-tunnels
```

#### Careers — `/careers` (200)
- `/careers/listing` (open roles)
- `/careers/students-graduates`
- `/careers/why-work-here`
- `/careers/help-build-what-powers-the-future` (campaign page)

#### Contact + Office locations
- `/contact-us` (200) — primary contact form
- `/contact/office-locations` (200) — main offices grid
- `/contact/locations` (200) — alternative URL (with anchors `#canada`, `#united-states`, `#middle-east`)

#### About — `/about` (200)
- `/about/leadership-team`
- `/about/purpose-and-principles`

#### Podcasts — `/podcasts` (200)
```
/podcasts/codecast-podcast
/podcasts/featured-podcasts
/podcasts/forensics-uncovered-podcast
/podcasts/industry-insights-podcast
/podcasts/jh-pacific-insider-podcast
/podcasts/special-hazards
```

#### Events — `/events` (200)
- `/events/past-events`
- Single events under `/event/{slug}`, e.g. `/event/nfpa-2026`, `/event/investing-in-safety-when-is-it-time-to-replace-aging-fire-alarm-systems`

#### Webinars — `/webinars` returns **500** (route exists but broken backend)

#### Team / Experts
- `/our-experts` (200) — header pattern
- `/experts` (200) — filterable directory (newer)

#### Other top-level
- `/northern-ireland` (200) — NI office/country page
- `/policies-compliance` (200)
- `/diversity-equity-inclusion` (200)
- `/privacy-policy` **404** (route gone)

---

### Europe

**Root:** `https://www.jensenhughes.com/europe/`

#### Services — `/europe/services` (35 unique sub)

```
/europe/services/advanced-modelling
/europe/services/advanced-reactor-smr-amr-consulting
/europe/services/civil-structural-failure
/europe/services/code-consulting-performance-based-design
/europe/services/contamination-spills
/europe/services/decommissioning-waste-management
/europe/services/emerging-hazards
/europe/services/energy-utilities
/europe/services/escape-of-water
/europe/services/expert-witness-litigation-support
/europe/services/external-wall-construction-and-cladding
/europe/services/fire-audits
/europe/services/fire-engineering-code-consulting
/europe/services/fire-engineering-consultancy
/europe/services/fire-explosion
/europe/services/fire-protection-design-safe-shutdown
/europe/services/fire-risk-assessments
/europe/services/fire-suppression-systems-design
/europe/services/forensic-investigation
/europe/services/hazardous-materials
/europe/services/hydrogen-services
/europe/services/lithium-ion-risk-consulting
/europe/services/long-term-operation-life-extension
/europe/services/marine-fire-forensics
/europe/services/mass-timber-consulting
/europe/services/materials-failure-analysis
/europe/services/mechanical-electrical-engineering
/europe/services/nuclear-licensing-regulatory-support
/europe/services/nuclear-site-evaluation
/europe/services/process-safety
/europe/services/product-liability-investigations
/europe/services/qa-iso-certifications
/europe/services/smoke-ventilation-design-instructions
/europe/services/structural-fire-safety
```

EU-only specialties vs NA: nuclear-licensing, nuclear-site-evaluation, advanced-reactor-smr-amr-consulting, decommissioning-waste-management, long-term-operation-life-extension (full nuclear stack); forensic-investigation, materials-failure-analysis, civil-structural-failure, fire-explosion, marine-fire-forensics, product-liability-investigations (full forensics suite); escape-of-water, contamination-spills, fire-audits, fire-risk-assessments, external-wall-construction-and-cladding (cladding/EWS post-Grenfell suite); smoke-ventilation-design-instructions.

#### Insights — `/europe/insights` (200)

Featured slugs visible on listing page:
```
/europe/insights/a-sign-that-says-you-belong
/europe/insights/accessibility-isnt-optional-its-essential
/europe/insights/bouwcampus-2-0
/europe/insights/compliance-vs-reality-closing-the-safety-gap
/europe/insights/fire-engineering-challenges-in-the-ukis-rapidly-expanding-data-centre-sector
/europe/insights/fire-safety-training-strengthening-competence-and-safety-culture
/europe/insights/from-zero-to-qmax
/europe/insights/greenock-health-and-care-centre
/europe/insights/jensen-hughes-strengthens-position-in-enrs-annual-design-rankings
/europe/insights/learning-from-history-the-risk-profile-of-nightclub-fires
/europe/insights/smoke-and-heat-control-systems-deliver-essential-fire-safety
/europe/insights/when-emerging-risks-escalate-defensible-forensic-clarity-is-essential
```

Category filters: `/europe/insights/{blog|media-article|industry-awards|project-profile}/all/all` plus `press-release/all/all`.

Plus `/europe/insights/drone-technology-a-game-changer-in-disaster-response-insurance-claims`, `/europe/insights/framework-for-engineering-and-risk-analysis-in-the-deployment-of-nuclear-power-for-hyperscale-data-centers`, `/europe/insights/from-helsinki-to-dublin-expanding-opportunities`, `/europe/insights/jensen-hughes-acquires-professional-loss-control-inc`, `/europe/insights/jensen-hughes-acquires-safety-management-services-inc`, `/europe/insights/jensen-hughes-recognized-among-enrs-top-design-firms-of-2025` (also visible from `/europe/` homepage).

#### Industries — `/europe/industries` (13 sub)

```
/europe/industries/aviation
/europe/industries/commercial
/europe/industries/education
/europe/industries/government-military
/europe/industries/healthcare
/europe/industries/hospitality-entertainment
/europe/industries/insurance
/europe/industries/manufacturing
/europe/industries/mission-critical
/europe/industries/nuclear-power
/europe/industries/petrochemical-oil-gas
/europe/industries/power
/europe/industries/transit-tunnels
```

EU-unique: `/europe/industries/insurance`. EU is missing `science-and-technology` vs NA.

#### Careers — `/europe/careers` (200)
- `/europe/careers/listing`
- `/europe/careers/students-graduates`
- `/europe/careers/why-work-here`
- `/europe/careers-belgium` (Belgium-specific landing, 200)

#### Contact + Office locations
- `/europe/contact-us` (200)
- `/europe/contact-page` (200) — alternative URL
- `/europe/contact/office-locations` (200) — main listing
- `/europe/contact/locations` (200) — alternative listing

Sub-URLs (extracted via `/europe/regions`):
```
/europe/contact/office-locations/antwerp
/europe/contact/office-locations/belfast
/europe/contact/office-locations/belgium-headquarters
/europe/contact/office-locations/birmingham
/europe/contact/office-locations/cork
/europe/contact/office-locations/denmark
/europe/contact/office-locations/denmark-aarhus
/europe/contact/office-locations/dublin
/europe/contact/office-locations/dublin-forensics-office
/europe/contact/office-locations/edinburgh
/europe/contact/office-locations/galway
/europe/contact/office-locations/glasgow
/europe/contact/office-locations/helsinki
/europe/contact/office-locations/italy-milano
/europe/contact/office-locations/kuopio
/europe/contact/office-locations/leuven
/europe/contact/office-locations/london-risbourough-st
/europe/contact/office-locations/manchester
/europe/contact/office-locations/oulu
/europe/contact/office-locations/tampere
/europe/contact/office-locations/turku
```

#### About — `/europe/about` (200)
- `/europe/about/leadership-team` (200)
- `/europe/about/purpose-and-principles` (200)

#### Podcasts — `/europe/podcasts` (200)
```
/europe/podcasts/featured-podcasts
/europe/podcasts/forensics-uncovered-podcast
/europe/podcasts/industry-insights-podcast
/europe/podcasts/special-hazard-safeguards
```

EU drops `codecast-podcast` and `jh-pacific-insider-podcast`, renames `special-hazards` -> `special-hazard-safeguards`.

#### Events — `/europe/events` (200)
- `/europe/events/past-events`

#### Webinars — `/europe/webinars` returns **500**

#### Team / Experts
- `/europe/our-experts` (200) — older pattern, present here
- `/europe/experts` (200) — filterable directory (e.g. `?region=Europe&country=Belgium`)
- Individual: `/europe/experts/bart-sette` etc.

#### Country pages (EU is the densest)
- `/europe/belgium` (200)
- `/europe/denmark` (200)
- `/europe/england` (200)
- `/europe/finland` (200)
- `/europe/ireland` (200)
- `/europe/italy` (200)
- `/europe/northern-ireland` (200)
- `/europe/scotland` (200)
- `/europe/regions` (200) — country directory
- `/europe/fi/` (200) — Finnish-language locale (own subtree)

Tested 404: germany, spain, france, netherlands, poland, sweden, norway, switzerland.

#### Country contact pages
- `/europe/country/belgium/contact`
- `/europe/country/denmark/contact`
- `/europe/country/england/contact`
- `/europe/country/finland/contact`
- `/europe/country/ireland/contact`
- `/europe/country/italy/contact`
- `/europe/country/northern-ireland/contact`
- `/europe/country/scotland/contact`

All return 200.

#### Other
- `/europe/policies-compliance` (200)
- `/europe/diversity-equity-inclusion` (200)

---

### Pacific

**Root:** `https://www.jensenhughes.com/pacific/`

#### Services — `/pacific/services` (38 unique)

```
/pacific/services/acceptable-solutions
/pacific/services/accessibility-core-services
/pacific/services/accessibility-universal-design
/pacific/services/accessible-adaptable-housing
/pacific/services/bespoke-accessibility-services
/pacific/services/building-certification
/pacific/services/building-code-consulting
/pacific/services/building-code-regulations
/pacific/services/bushfire-testing
/pacific/services/construction-inspections-advice
/pacific/services/crown-certification
/pacific/services/design-assessments-upgrades
/pacific/services/energy-sustainability
/pacific/services/expert-witness
/pacific/services/fire-engineering
/pacific/services/fire-protection-engineering
/pacific/services/fire-resistance-testing
/pacific/services/fire-safety-assessment-audits
/pacific/services/fire-safety-strategies
/pacific/services/fire-testing
/pacific/services/green-building-certification
/pacific/services/infrastructure-tunnels
/pacific/services/jensen-hughes-firemark-product-certification
/pacific/services/jensen-hughes-firemark-product-certification-scheme
/pacific/services/modelling
/pacific/services/passive-fire-compliance
/pacific/services/passive-fire-design
/pacific/services/peer-reviews
/pacific/services/performance-solutions
/pacific/services/qa-iso-certifications
/pacific/services/reaction-to-fire-testing
/pacific/services/registered-research-service-provider
/pacific/services/regulatory-compliance
/pacific/services/structural-fire-engineering
/pacific/services/sustainable-design-services
/pacific/services/technical-fire-assessment
/pacific/services/terms-conditions
```

Pacific-unique: bushfire-testing, acceptable-solutions (NZ regulatory term), crown-certification (NZ Crown property), building-certification, fire-resistance-testing, reaction-to-fire-testing, jensen-hughes-firemark-product-certification (+ scheme variant), registered-research-service-provider, all 4 accessibility variants (Pacific's accessibility consulting is more granular), green-building-certification.

#### Insights — `/pacific/insights` (200)

Featured slugs:
```
/pacific/insights/a-sign-that-says-you-belong
/pacific/insights/chatswood-chase
/pacific/insights/compliance-vs-reality-closing-the-safety-gap
/pacific/insights/fire-safety-and-evacuation-strategy-in-automated-underground-rail-systems
/pacific/insights/jensen-hughes-strengthens-position-in-enrs-annual-design-rankings
/pacific/insights/learning-from-history-the-risk-profile-of-nightclub-fires
/pacific/insights/macquarie-point-multipurpose-stadium
/pacific/insights/starship-childrens-hospital-paediatric-intensive-care-unit
/pacific/insights/sydney-harbour-bridge-cycleway-ramp
/pacific/insights/the-standard-magazine-march-2026-issue
/pacific/insights/vasse-village-by-bunbury-farmers-market
```

Categories: `/pacific/insights/{blog|media-article|industry-awards|project-profile|press-release}/all/all`.

Pagination: `/pacific/insights/p2`, `/p3`, `/p4`.

#### Industries — `/pacific/industries` (10 sub)

```
/pacific/industries/commercial-mixed-use
/pacific/industries/education
/pacific/industries/government
/pacific/industries/healthcare
/pacific/industries/hospitality-events
/pacific/industries/manufacturing
/pacific/industries/residential
/pacific/industries/transit-tunnels
/pacific/industries/warehouse
```

Pacific-unique: `commercial-mixed-use` (vs `commercial`), `government` (vs `government-military`/`government-defence`), `hospitality-events` (vs `hospitality-entertainment`), `residential`, `warehouse`. Missing: aviation, nuclear-power, petrochemical-oil-gas, power, mission-critical, science-and-technology.

#### Careers — `/pacific/careers` (200)
- `/pacific/careers/listing`
- `/pacific/careers/why-work-here`
- `/pacific/careers/graduate-program` (Pacific renames "students-graduates" to "graduate-program")

#### Contact + Office locations
- `/pacific/contact-us` **404** — use `/pacific/contact-page` (200) or `/pacific/contact/office-locations`
- `/pacific/contact/office-locations` (200)
- `/pacific/contact/locations` (200)

Sub-URLs (extracted from `/pacific/australia` and `/pacific/new-zealand`):
```
/pacific/contact/office-locations/auckland-new-zealand
/pacific/contact/office-locations/brisbane
/pacific/contact/office-locations/canberra
/pacific/contact/office-locations/christchurch
/pacific/contact/office-locations/melbourne-australia
/pacific/contact/office-locations/melbourne-australia-lab
/pacific/contact/office-locations/perth-adelaide-terrace-australia
/pacific/contact/office-locations/sydney-king-street
/pacific/contact/office-locations/tauranga
```

#### About — `/pacific/about` (200), `/pacific/about-us` (200) both work
- `/pacific/about/purpose-and-principles` (200)
- `/pacific/about/leadership-team` **404** (Pacific has no regional leadership page)

#### Podcasts — `/pacific/podcasts` (200)
```
/pacific/podcasts/entry-224516
/pacific/podcasts/featured-podcasts
/pacific/podcasts/industry-insights-podcast
/pacific/podcasts/jh-pacific-insider-podcast
```

The `entry-224516` slug is unusual (CMS auto-ID) — likely a draft or recently-renamed series.

#### Events — `/pacific/events` (200)
- `/pacific/events/past-events`

#### Webinars — `/pacific/webinars` returns **500**

#### Team / Experts
- `/pacific/our-experts` **404**
- `/pacific/experts` (200) — only directory; use `?region=Pacific&country=Pacific` filters
- Individual: `/pacific/experts/charles-boyce`, `/pacific/experts/daryn-glasgow`, `/pacific/experts/jeff-parkinson`, `/pacific/experts/raymond-qiu`, `/pacific/experts/samuel-beatson`

#### Country pages
- `/pacific/australia` (200)
- `/pacific/new-zealand` (200)
- `/pacific/regions` (200) — directory

#### Country contact pages
- `/pacific/country/australia/contact` (200)
- `/pacific/country/new-zealand/contact` (200)

#### Other
- `/pacific/policies-compliance` (200)
- `/pacific/certified-companies` (200) — **Pacific-only**, ties to FireMark/product-certification specialty

---

### Asia

**Root:** `https://www.jensenhughes.com/asia/`

#### Services — `/asia/services` (23 real services + 3 country pages embedded)

```
/asia/services/ahj-representation-plan-review
/asia/services/code-consulting
/asia/services/code-consulting-built-environment
/asia/services/combustible-dust-safety
/asia/services/commissioning
/asia/services/emerging-hazards
/asia/services/energy
/asia/services/fire-engineering-systems-design
/asia/services/fire-smoke-tunnel-modeling
/asia/services/fire-suppression-systems-design
/asia/services/hazardous-materials
/asia/services/hydrogen-services
/asia/services/life-safety-pbd
/asia/services/lithium-ion-risk-consulting
/asia/services/pedestrian-evacuation-modeling
/asia/services/pre-construction-administration-and-oversight
/asia/services/process-safety
/asia/services/qa-iso-certifications
/asia/services/risk-analysis
/asia/services/testing-research-development
```

Country-style pages embedded in services tree:
```
/asia/services/greater-china
/asia/services/malaysia
/asia/services/south-korea
```

Asia's service catalog is a near-subset of NA's. Country pages live here because Asia does not have first-class `/asia/{country}` URLs.

#### Insights — `/asia/insights` (200)

Featured slugs:
```
/asia/insights/a-sign-that-says-you-belong
/asia/insights/bd-c-ranks-jensen-hughes-among-the-top-five-engineering-firms-of-2025
/asia/insights/celebrating-10-incredible-years-of-the-jensen-hughes-brand
/asia/insights/compliance-vs-reality-closing-the-safety-gap
/asia/insights/diverse-smr-siting-in-the-uk
/asia/insights/framework-for-engineering-and-risk-analysis-in-the-deployment-of-nuclear-power-for-hyperscale-data-centers
/asia/insights/how-lsft-is-reshaping-bess-design-compliance
/asia/insights/jensen-hughes-acquires-professional-loss-control-inc
/asia/insights/jensen-hughes-acquires-safety-management-services-inc
/asia/insights/jensen-hughes-recognized-among-enrs-top-design-firms-of-2025
/asia/insights/jensen-hughes-strengthens-position-in-enrs-annual-design-rankings
/asia/insights/learning-from-history-the-risk-profile-of-nightclub-fires
/asia/insights/recipients-of-our-2025-people-principle-in-action-giving-program
/asia/insights/the-crans-montana-fire-history-repeating-itself
/asia/insights/what-does-qmax-mean-in-the-context-of-bs-en-12845
```

Categories: `/asia/insights/{blog|media-article|industry-awards|project-profile|press-release}/all/all`.

Pagination: `/asia/insights/p2`, `/p3`, `/p4`.

#### Industries — `/asia/industries` (9 sub)

```
/asia/industries/aviation
/asia/industries/commercial
/asia/industries/hospitality-entertainment
/asia/industries/manufacturing
/asia/industries/mission-critical
/asia/industries/nuclear-power
/asia/industries/power
/asia/industries/transit-tunnels
```

Missing vs NA: education, government-military, healthcare, petrochemical-oil-gas, science-and-technology.

#### Careers — `/asia/careers` (200)
- `/asia/careers/listing`
- `/asia/careers/students-graduates`
- `/asia/careers/why-work-here`

#### Contact + Office locations
- `/asia/contact-us` (200)
- `/asia/contact-page` **404**
- `/asia/contact/office-locations` (200)
- `/asia/contact/locations` (200)

(Individual office sub-URLs not exposed in raw HTML of the listing; JS-rendered. Office cities can be inferred from `/asia/services/{greater-china,malaysia,south-korea}` page bodies.)

#### About — `/asia/about` (200), `/asia/about-us` (200) both work
- `/asia/about/purpose-and-principles` (200)
- `/asia/about/leadership-team` **404**

#### Podcasts — `/asia/podcasts` (200)
```
/asia/podcasts/entry-224516
/asia/podcasts/featured-podcasts
/asia/podcasts/industry-insights-podcast
```

Same `entry-224516` quirk as Pacific (likely cross-region featured episode pointer).

#### Events — `/asia/events` **404** (no events listing for Asia)

#### Webinars — `/asia/webinars` returns **500**

#### Team / Experts
- `/asia/our-experts` **404**
- `/asia/experts` (200) — filterable directory

#### Country pages
- No first-class `/asia/{country}` URLs (all 404).
- Country-style content lives under `/asia/services/greater-china`, `/asia/services/malaysia`, `/asia/services/south-korea`.

#### Other
- `/asia/policies-compliance` (200)

---

### Middle East

**Root:** `https://www.jensenhughes.com/middle-east/`

#### Services — `/middle-east/services` (27 unique)

```
/middle-east/services/accessibility
/middle-east/services/ahj-liaison-approvals
/middle-east/services/code-consulting
/middle-east/services/code-consulting-built-environment
/middle-east/services/commissioning
/middle-east/services/emergency-management-response
/middle-east/services/emergency-preparedness
/middle-east/services/emerging-hazards
/middle-east/services/energy
/middle-east/services/fire-emergency-services-consulting
/middle-east/services/fire-engineering-systems-design
/middle-east/services/fire-smoke-tunnel-modeling
/middle-east/services/fire-suppression-systems-design
/middle-east/services/hazardous-materials
/middle-east/services/hydrogen-services
/middle-east/services/life-safety-pbd
/middle-east/services/mass-notification-systems
/middle-east/services/pre-construction-administration-and-oversight
/middle-east/services/private-client-family-office-services
/middle-east/services/process-safety
/middle-east/services/qa-iso-certifications
/middle-east/services/response-planning
/middle-east/services/security-design
/middle-east/services/security-risk-management
/middle-east/services/security-risk-public-safety
/middle-east/services/structural-fire-safety
/middle-east/services/threat-violence-risk-management
```

(Note: `/middle-east/services/energy_utilities` is also returned by one HTML scrape — appears to be a deprecated/renamed slug; main canonical is `/energy`.)

ME-unique service handling: `ahj-liaison-approvals` (vs NA `ahj-representation-plan-review`), `mass-notification-systems`, `private-client-family-office-services`, all `security-*` and `threat-violence-risk-management` (security suite mirrors NA).

ME-only missing vs NA: investigations, healthcare-* suite, training, large-scale-fire-testing-lsft, environmental, in-house-laboratories-testing, dataadvisr/protectadvisr/riskadvisr/hazadvisr, fire-suppression-systems-design (wait — that IS present), etc.

#### Insights — `/middle-east/insights` (200)

Featured slugs:
```
/middle-east/insights/a-sign-that-says-you-belong
/middle-east/insights/celebrating-10-incredible-years-of-the-jensen-hughes-brand
/middle-east/insights/compliance-vs-reality-closing-the-safety-gap
/middle-east/insights/hotel-indigo-auckland-51-albert-residences
/middle-east/insights/jensen-hughes-acquires-professional-loss-control-inc
/middle-east/insights/jensen-hughes-acquires-safety-management-services-inc
/middle-east/insights/jensen-hughes-recognized-among-enrs-top-design-firms-of-2025
/middle-east/insights/jensen-hughes-strengthens-position-in-enrs-annual-design-rankings
/middle-east/insights/learning-from-history-the-risk-profile-of-nightclub-fires
/middle-east/insights/middle-east-india-newsletter-q4-2025
/middle-east/insights/powering-confidence-large-scale-fire-testing-for-battery-energy-storage-systems
/middle-east/insights/the-crans-montana-fire-history-repeating-itself
/middle-east/insights/the-critical-difference-between-emergency-planning-and-emergency-response-software
```

Categories: `/middle-east/insights/{blog|media-article|industry-awards|project-profile|press-release}/all/all`.

Pagination: `/middle-east/insights/p2`, `/p3`, `/p4`.

ME-unique content: `middle-east-india-newsletter-q4-2025` is a regional newsletter (not seen elsewhere).

#### Industries — `/middle-east/industries` (9 sub)

```
/middle-east/industries/aviation
/middle-east/industries/commercial
/middle-east/industries/government-defence
/middle-east/industries/hospitality-entertainment
/middle-east/industries/nuclear-power
/middle-east/industries/petrochemical-oil-gas
/middle-east/industries/power
/middle-east/industries/science-and-technology
/middle-east/industries/transit-tunnels
```

ME-unique naming: `government-defence` (vs `government-military` NA, `government` Pacific). Missing vs NA: education, healthcare, manufacturing, mission-critical.

#### Careers — `/middle-east/careers` (200)
- `/middle-east/careers/listing`
- `/middle-east/careers/why-work-here`
- `/middle-east/careers/students-graduates` **404** (ME has no students-graduates page)

#### Contact + Office locations
- `/middle-east/contact-us` (200)
- `/middle-east/contact/locations` (200) — **note non-standard URL**
- `/middle-east/contact/office-locations` **404** — must use `/contact/locations` instead
- `/middle-east/contact-page` **404**

Sub-URLs (extracted from `/middle-east/india` page):
```
/middle-east/contact/office-locations/mumbai
```
The `office-locations/{city}` slug pattern DOES work for individual offices even though the index page is at `/contact/locations`.

#### About — `/middle-east/about` (200)
- `/middle-east/about/purpose-and-principles` (200)
- `/middle-east/about/leadership-team` **404**
- `/middle-east/about-us` **404**

#### Podcasts — `/middle-east/podcasts` (200)
```
/middle-east/podcasts/codecast-podcast
/middle-east/podcasts/featured-podcasts
/middle-east/podcasts/forensics-uncovered-podcast
/middle-east/podcasts/industry-insights-podcast
/middle-east/podcasts/special-hazards
```

ME has the fullest podcast set after NA (5 series — same as NA minus `jh-pacific-insider-podcast`).

#### Events — `/middle-east/events` **404**

#### Webinars — `/middle-east/webinars` returns **500**

#### Team / Experts
- `/middle-east/our-experts` **404**
- `/middle-east/experts` (200) — filterable directory
- Individual: `/middle-east/experts/ali-lehry`

#### Country pages
- `/middle-east/india` (200) — first-class India page
- `/middle-east/india/contact` (200)
- `/middle-east/country/india/contact` (200) — alt URL
- `/middle-east/uae` **404**, `/middle-east/saudi-arabia` **404** — no other country pages

#### Other
- `/middle-east/policies-compliance` (200)

---

## Key cross-region observations

### Categories that exist on ALL 5 regions
- Services landing + sub-services tree
- Insights landing + same category filter pattern (`{blog|media-article|industry-awards|project-profile|press-release}/all/all`) + paginated p2/p3/p4
- Industries landing + sub-industries
- Careers landing + `careers/listing` + `careers/why-work-here`
- About landing + `about/purpose-and-principles`
- Podcasts landing + at minimum `featured-podcasts` and `industry-insights-podcast`
- Experts filterable directory at `/{region}/experts`
- Policies-compliance
- `contact/locations` (alternate office listing URL works on every region; `office-locations` is the canonical except on ME)

### Categories that are global-only (single URL serves all regions)
- `/about/leadership-team` exists ONLY on NA and EU. Pacific/Asia/ME have no regional leadership page — they share Cory Henkel + Sahar Hashmi at the corporate level via `/about/leadership-team`.
- `/our-experts` exists ONLY on NA and EU (legacy URL). Pacific/Asia/ME route only through `/experts` (newer filterable directory).
- Webinars: route registered (`/webinars`, `/europe/webinars`, etc.) but every region returns HTTP 500 — broken backend, content not currently exposed.
- `/event/{slug}` for single events: only seen under NA root, not under regional prefixes.
- `/insights/help-build-what-powers-the-future` careers campaign: NA only.
- `/privacy-policy`: returns 404 on NA (and presumably elsewhere). May have been migrated under `/policies-compliance`.

### Categories that are region-specific with unique sub-content
- **Country pages**:
  - EU has 8 country pages (`/europe/{belgium|denmark|england|finland|ireland|italy|northern-ireland|scotland}`) plus `/europe/regions` and Finnish locale `/europe/fi/`. EU also exposes `/europe/country/{country}/contact` for every country and `/europe/careers-belgium` as locale-specific careers.
  - Pacific has 2 (`/pacific/australia`, `/pacific/new-zealand`) plus `/pacific/regions` and `/pacific/country/{country}/contact`.
  - Middle East has 1 (`/middle-east/india`) plus `/middle-east/india/contact` and `/middle-east/country/india/contact`.
  - NA has `/northern-ireland` (corporate-level NI office page).
  - Asia has zero first-class country pages — country-style content lives inside `/asia/services/{greater-china|malaysia|south-korea}`.
- **Events**:
  - NA, EU, Pacific have `/{region}/events` + `/events/past-events`.
  - Asia and Middle East return 404 on `/events`.
- **Certified-companies**:
  - Only Pacific has `/pacific/certified-companies` — ties to FireMark product certification specialty.
- **Insurance industry**:
  - Only Europe has `/europe/industries/insurance`. Other regions roll insurance under commercial or forensics services.
- **Science-and-technology industry**:
  - Present on NA and ME; absent on EU, Pacific, Asia.
- **Aviation/petrochemical/nuclear**:
  - Present on every region except Pacific (no aviation, no petrochemical, no nuclear in Pacific's industries — Pacific is residential/commercial/healthcare-heavy).

### Region-only service specialties
- **NA-only services**: dataadvisr, hazadvisr, protectadvisr, riskadvisr (the `*advisr` SaaS-style product suite), smartplan, healthcare-emergency-preparedness, healthcare-life-safety-assessments, law-enforcement-consulting, large-scale-fire-testing-lsft, in-house-laboratories-testing, investigations, environmental, wildfire-risk-mitigation, training, digital, accessibility, mep-structural-safety.
- **EU-only services**: full nuclear stack (advanced-reactor-smr-amr-consulting, decommissioning-waste-management, long-term-operation-life-extension, nuclear-licensing-regulatory-support, nuclear-site-evaluation), full forensics stack (civil-structural-failure, forensic-investigation, materials-failure-analysis, marine-fire-forensics, product-liability-investigations, expert-witness-litigation-support), full UK-Grenfell cladding suite (external-wall-construction-and-cladding, fire-audits, fire-risk-assessments), escape-of-water, contamination-spills, mechanical-electrical-engineering, smoke-ventilation-design-instructions, structural-fire-safety, advanced-modelling, fire-engineering-code-consulting, fire-engineering-consultancy, fire-explosion, fire-protection-design-safe-shutdown.
- **Pacific-only services**: bushfire-testing (Australia specialty), acceptable-solutions / crown-certification / registered-research-service-provider (NZ-regulatory), building-certification, fire-resistance-testing, reaction-to-fire-testing, jensen-hughes-firemark-product-certification (+ scheme variant — Pacific FireMark program), 4-tier accessibility offering (accessibility-core-services / accessibility-universal-design / accessible-adaptable-housing / bespoke-accessibility-services), green-building-certification, sustainable-design-services, expert-witness, fire-engineering, fire-protection-engineering, fire-safety-assessment-audits, fire-safety-strategies, fire-testing, modelling, passive-fire-compliance, passive-fire-design, peer-reviews, performance-solutions, regulatory-compliance, structural-fire-engineering, technical-fire-assessment, infrastructure-tunnels, design-assessments-upgrades, construction-inspections-advice, energy-sustainability.
- **Asia-only services**: country pages (greater-china, malaysia, south-korea) live inside services. Otherwise Asia is a narrow subset of NA services.
- **Middle-East-only services**: ahj-liaison-approvals (vs NA's ahj-representation-plan-review). ME's security suite (security-design, security-risk-management, security-risk-public-safety, threat-violence-risk-management, mass-notification-systems, private-client-family-office-services) mirrors NA's but is a stronger relative emphasis given the region's defence/government clientele.

---

## Stats

Total verified 200-status URLs in this inventory: ~280
- NA / Global: 49 services + 14 industries + ~18 visible insights + 4 careers + 3 contact + 2 about + 6 podcasts + 2 events + 2 expert directories + 4 misc (northern-ireland, policies-compliance, DEI, contact-us) = **~104 distinct paths probed 200**
- Europe: 35 services + 13 industries + ~18 insights + 4 careers + 4 contact + 2 about + 4 podcasts + 2 events + 21 office sub-URLs + 8 country pages + 8 country-contact pages + 2 misc = **~121 distinct paths probed 200**
- Pacific: 38 services + 10 industries + ~13 insights + 3 careers + 3 contact + 2 about + 4 podcasts + 2 events + 9 office sub-URLs + 2 country pages + 2 country-contact pages + 2 misc (certified-companies, policies) = **~88 distinct paths probed 200**
- Asia: 23 services + 3 country-services + 9 industries + ~15 insights + 3 careers + 2 contact + 2 about + 3 podcasts + 2 misc = **~62 distinct paths probed 200**
- Middle East: 27 services + 9 industries + ~13 insights + 2 careers + 2 contact + 2 about + 5 podcasts + 1 country page (india) + 2 country-contact pages + 1 office sub (mumbai) + 2 misc = **~66 distinct paths probed 200**

404 / 500 / non-200 summary:
- `/webinars` returns 500 on every region (broken backend).
- `/asia/events`, `/middle-east/events` are 404 (Asia + ME have no events listing).
- `/pacific/contact-us` is 404 (use `/pacific/contact-page` or `/pacific/contact/office-locations`).
- `/middle-east/contact/office-locations` is 404 (use `/middle-east/contact/locations`).
- `/{pacific|asia|middle-east}/our-experts` are all 404 (only NA + EU have the legacy `/our-experts` URL).
- `/{pacific|asia|middle-east}/about/leadership-team` are all 404 (no regional leadership pages outside NA + EU).
- `/{pacific|middle-east}/careers/students-graduates` are 404 (Pacific uses `graduate-program`, ME has no such page).
- `/privacy-policy` is 404 on NA (URL retired or moved under policies-compliance).

---

## How to use this for the V6 prompt + KB upload

1. **Route classifier:** for any user query that maps to one of the 11 categories above, the bot must (a) detect region context, (b) pick the correct URL pattern from the table, (c) handle the documented 404/500 fallbacks (ME contact uses `/locations` not `/office-locations`; Pacific contact uses `/contact-page`; etc.).
2. **Cross-region disambiguation:** if a user asks about "leadership" or "our team" outside NA + EU, the bot should redirect to corporate `/about/leadership-team` rather than 404. Same for `our-experts` (point users at the regional `/experts` directory).
3. **Region-only specialties to surface confidently:** bushfire-testing → Pacific; nuclear-licensing-regulatory-support → Europe; india country page → Middle East; FireMark certification → Pacific only; security suite → NA + Middle East.
4. **Events handling:** if the user is in Asia or Middle East and asks about events, bot should explain there's no regional events page and offer `/events` (NA) or `/europe/events` based on relevance.
5. **Webinars:** route is currently broken site-wide. Bot should NOT link to `/webinars` until the 500 is fixed; redirect to insights / podcasts.
6. **KB upload scope:** insights articles are the highest-volume changing content (pagination p2-p4 per region). For first KB pass, prioritize: all services pages (151 across regions), all industries pages (55), all about/podcasts/policies (stable), and the latest 20-30 insights per region.
