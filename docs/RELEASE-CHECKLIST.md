# Public release checklist

This source is an **unreleased review candidate**, preserved from the original plugin v0.3.0.

- [x] Inventory complete; original source preserved
- [x] PHP syntax check passed locally on PHP 8.4
- [x] Three JavaScript syntax checks passed locally on Node 22
- [x] Bundled Razorpay PHP SDK version identified as 2.9.3
- [x] No literal Razorpay live/test credentials found by an initial pattern scan
- [ ] Resolve payment lifecycle and concurrency review findings
- [ ] Review payment amount source, legacy form migration and cancellation policy
- [ ] Test on current JetFormBuilder version and all declared PHP versions
- [ ] Complete Razorpay Test Mode happy and failure paths
- [ ] Obtain independent review of payment security and privacy/data minimization
- [ ] Confirm third-party source/provenance and copyright for all originally authored files
- [ ] Select and add explicit license for original plugin code
- [ ] Add successful integration test evidence and update compatibility table
- [ ] Publish repository only after release blockers are addressed
- [ ] Tag first release only after a clean staging installation passes
