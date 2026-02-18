#!/usr/bin/env bash
set -e

DEMO="./demo/demo"

echo "════════════════════════════════════════════════════════════════"
echo "  Scenario 6: Offline Mode and Synchronization"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "Demonstrates offline order creation and later synchronization"
echo ""

$DEMO state clear

echo ""
echo "Step 1: Setup"
$DEMO terminal register --name="POS-06"
$DEMO shift open --opening-cash=35000
$DEMO session start

echo ""
echo "Step 2: Create order OFFLINE (network unavailable)"
echo "(Order is queued for sync, not yet in ordering BC)"
$DEMO session new-order-offline

echo ""
echo "Step 3: Create another offline order"
$DEMO session new-order-offline

echo ""
echo "Step 4: Network restored - sync first order"
ORDER_1=$(cat demo/data/demo-state.json | grep -o '"order_ids":\[[^]]*\]' | grep -o '"[^"]*"' | sed -n '2p' | tr -d '"')
$DEMO session sync --order-id="$ORDER_1"

echo ""
echo "Step 5: Sync second order"
ORDER_2=$(cat demo/data/demo-state.json | grep -o '"order_ids":\[[^]]*\]' | grep -o '"[^"]*"' | sed -n '3p' | tr -d '"')
$DEMO session sync --order-id="$ORDER_2"

echo ""
echo "Step 6: Complete first order (now online)"
$DEMO session resume --order-id="$ORDER_1"
$DEMO session checkout
$DEMO session pay --amount=9500 --method=cash
$DEMO session complete

echo ""
echo "════════════════════════════════════════════════════════════════"
echo "  Scenario 6 Complete!"
echo "════════════════════════════════════════════════════════════════"
echo ""
