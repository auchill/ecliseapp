# Stage 2 Known Limitations

- Reconciliation is local-only and does not query Stripe or PayPal remote ledgers.
- Gateway refunds are not automated; refunds are recorded manually.
- Fine-grained payment permission checks are seeded but not fully enforced per action beyond admin middleware.
- PDF invoice rendering is browser print based in this stage.
- Automated correction mode for reconciliation is intentionally disabled.
