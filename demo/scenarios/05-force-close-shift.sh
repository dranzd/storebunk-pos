#!/usr/bin/env bash
set -e

DEMO="./demo/demo"

echo "════════════════════════════════════════════════════════════════"
echo "  Scenario 5: Force Close Shift"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "Demonstrates supervisor force-closing a shift (emergency scenario)"
echo ""

$DEMO state clear

echo ""
echo "Step 1: Setup"
$DEMO terminal register --name="POS-05"
$DEMO shift open --opening-cash=40000

echo ""
echo "Step 2: Start session and process an order"
$DEMO session start
$DEMO session new-order
$DEMO session checkout
$DEMO session pay --amount=8000 --method=cash
$DEMO session complete

echo ""
echo "Step 3: Simulate emergency - supervisor force-closes shift"
echo "(Bypasses normal close validation)"
$DEMO shift force-close --supervisor-id="MGR-001" --reason="Emergency evacuation"

echo ""
echo "════════════════════════════════════════════════════════════════"
echo "  Scenario 5 Complete!"
echo "════════════════════════════════════════════════════════════════"
echo ""
