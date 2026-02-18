#!/usr/bin/env bash
set -e

DEMO="./demo/demo"

echo "════════════════════════════════════════════════════════════════"
echo "  Scenario 7: Concurrency Conflict Detection"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "Demonstrates optimistic locking and concurrency conflict detection"
echo "Note: This scenario requires manual intervention to simulate concurrent"
echo "modifications. The demo uses in-memory storage, so true concurrency"
echo "cannot be demonstrated in a single script."
echo ""
echo "To test concurrency:"
echo "1. Open two terminal windows"
echo "2. In both, run: ./demo terminal register --name='POS-07'"
echo "3. Load the same terminal in both"
echo "4. Modify in window 1, then modify in window 2"
echo "5. The second modification should detect a version conflict"
echo ""
echo "For this demo, we'll show the sequential flow that WOULD conflict:"
echo ""

$DEMO state clear

echo ""
echo "Step 1: Register terminal"
$DEMO terminal register --name="POS-07"

echo ""
echo "Step 2: Get terminal details (version = 1)"
$DEMO terminal get

echo ""
echo "Step 3: Activate terminal (version = 2)"
$DEMO terminal activate

echo ""
echo "Step 4: Get terminal details (version = 2)"
$DEMO terminal get

echo ""
echo "Step 5: Disable terminal (version = 3)"
$DEMO terminal disable

echo ""
echo "Step 6: Get terminal details (version = 3)"
$DEMO terminal get

echo ""
echo "════════════════════════════════════════════════════════════════"
echo "  Scenario 7 Complete!"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "Note: True concurrency conflicts require running commands in parallel."
echo "The InMemoryEventStore tracks versions and will throw ConcurrencyException"
echo "when expectedVersion doesn't match currentVersion."
echo ""
