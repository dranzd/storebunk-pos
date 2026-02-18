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
echo "Step 3: Park Order (simulate inactivity)"
$DEMO session park

echo ""
echo "Step 4: Simulate TTL expiry - order gets deactivated"
echo "(In production, DraftLifecycleService would detect this)"
echo "For demo, we manually trigger reactivation to show the flow"

echo ""
echo "Step 5: Attempt to reactivate order (inventory re-reservation)"
ORDER_ID=$(cat demo/data/demo-state.json | grep -o '"last_order_id":"[^"]*"' | cut -d'"' -f4)
$DEMO session reactivate --order-id="$ORDER_ID"

echo ""
echo "Step 6: Complete the reactivated order"
$DEMO session resume --order-id="$ORDER_ID"
$DEMO session checkout
$DEMO session pay --amount=12000 --method=cash
$DEMO session complete

echo ""
echo "════════════════════════════════════════════════════════════════"
echo "  Scenario 4 Complete!"
echo "════════════════════════════════════════════════════════════════"
echo ""
