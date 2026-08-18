#!/usr/bin/env bash
set -e

DEMO="./demo/demo"

echo "════════════════════════════════════════════════════════════════"
echo "  Scenario 4: Draft TTL Expiry and Reactivation"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "Demonstrates order deactivation (TTL expiry) and reactivation"
echo ""

$DEMO state clear

echo ""
echo "Step 1: Setup"
$DEMO terminal register --name="POS-04"
$DEMO shift open --opening-cash=25000
$DEMO session start

echo ""
echo "Step 2: Start New Order"
$DEMO session new-order

echo ""
echo "Step 3: Simulate TTL expiry - order gets deactivated"
echo "(In production, DraftLifecycleService detects the inactivity and"
echo " dispatches DeactivateOrder; for the demo we trigger it directly)"
ORDER_ID=$(php -r 'echo json_decode(file_get_contents((getenv("POS_DEMO_DATA_DIR") ?: "demo/data") . "/demo-state.json"), true)["last_order_id"] ?? "";')
$DEMO session deactivate --reason="TTL expired (demo)"

echo ""
echo "Step 4: Reactivate order (inventory re-reservation)"
$DEMO session reactivate --order-id="$ORDER_ID"

echo ""
echo "Step 5: Complete the reactivated order"
$DEMO session checkout
$DEMO session pay --amount=12000 --method=cash
$DEMO session complete

echo ""
echo "════════════════════════════════════════════════════════════════"
echo "  Scenario 4 Complete!"
echo "════════════════════════════════════════════════════════════════"
echo ""
