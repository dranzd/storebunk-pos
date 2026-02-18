#!/usr/bin/env bash
set -e

DEMO="./demo/demo"

echo "════════════════════════════════════════════════════════════════"
echo "  Scenario 3: Park and Resume Orders"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "Demonstrates parking an order and resuming it later"
echo ""

$DEMO state clear

echo ""
echo "Step 1: Setup"
$DEMO terminal register --name="POS-03"
$DEMO shift open --opening-cash=20000
$DEMO session start

echo ""
echo "Step 2: Start Order #1"
$DEMO session new-order

echo ""
echo "Step 3: Park Order #1 (customer needs time to decide)"
$DEMO session park

echo ""
echo "Step 4: Start Order #2 (serve another customer)"
$DEMO session new-order

echo ""
echo "Step 5: Park Order #2"
$DEMO session park

echo ""
echo "Step 6: Resume Order #1 (first customer returns)"
ORDER_1=$(cat demo/data/demo-state.json | grep -o '"order_ids":\[[^]]*\]' | grep -o '"[^"]*"' | sed -n '2p' | tr -d '"')
$DEMO session resume --order-id="$ORDER_1"

echo ""
echo "Step 7: Complete Order #1 checkout"
$DEMO session checkout
$DEMO session pay --amount=10000 --method=cash
$DEMO session complete

echo ""
echo "Step 8: Resume Order #2 (second customer)"
ORDER_2=$(cat demo/data/demo-state.json | grep -o '"order_ids":\[[^]]*\]' | grep -o '"[^"]*"' | sed -n '3p' | tr -d '"')
$DEMO session resume --order-id="$ORDER_2"

echo ""
echo "Step 9: Complete Order #2"
$DEMO session checkout
$DEMO session pay --amount=15000 --method=card
$DEMO session complete

echo ""
echo "════════════════════════════════════════════════════════════════"
echo "  Scenario 3 Complete!"
echo "════════════════════════════════════════════════════════════════"
echo ""
