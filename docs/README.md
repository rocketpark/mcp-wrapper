# Documentation Organization

This directory contains supplementary documentation for the MCP Wrapper plugin.

## Structure

### `/archive/`
Historical documentation that has been superseded but retained for reference:

- **ACCURATE-IMPROVEMENTS-V2.md** - Deep dive security/performance analysis (Feb 2, 2026)
- **IMPROVEMENT-RECOMMENDATIONS.md** - Original improvement proposals (now in CHANGELOG)
- **V2.2-IMPLEMENTATION-SUMMARY.md** - v2.2 implementation details (now in CHANGELOG)
- **RECENT-CHANGES-SUMMARY.md** - v2.2-v2.7 changes summary (now in CHANGELOG)
- **FINAL-TEST-RESULTS.md** - January 26, 2026 test results
- **REGIONAL-LEADERSHIP-TESTING-GUIDE.md** - Technical debugging guide for Regional Leadership filtering

### `/examples/`
Code examples and integration patterns:

- **WEBHOOK-EXAMPLES.md** - Webhook integration examples for Botpress, Zapier, Make.com, etc.

## Core Documentation

The following files remain in the project root and constitute the primary documentation:

- **[README.md](../README.md)** - Main documentation with architecture diagrams
- **[DEPLOYMENT.md](../DEPLOYMENT.md)** - Production deployment guide
- **[VERIFICATION.md](../VERIFICATION.md)** - Post-deployment verification checklist
- **[CHANGELOG.md](../CHANGELOG.md)** - Version history and release notes
- **[JENSEN-HUGHES-IMPLEMENTATION.md](../JENSEN-HUGHES-IMPLEMENTATION.md)** - Real-world implementation guide
- **[COMPREHENSIVE-BOT-QUESTIONS.md](../COMPREHENSIVE-BOT-QUESTIONS.md)** - 200+ AI assistant test questions

## Contributing

When adding new documentation:

- **Implementation guides** → Add to root with descriptive filename
- **Code examples** → Add to `/examples/`
- **Historical references** → Add to `/archive/` with clear date context
- **Architecture diagrams** → Update README.md mermaid charts

## Questions?

For implementation questions, see [JENSEN-HUGHES-IMPLEMENTATION.md](../JENSEN-HUGHES-IMPLEMENTATION.md) which provides a complete walkthrough of a production deployment.
