#!/usr/bin/env bash
set -e

DEMO="./demo/demo"

echo "════════════════════════════════════════════════════════════════"
echo "  Scenario 2: Checkout Flow"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "Demonstrates the complete checkout flow: draft → confirmed → paid → completed"
echo ""

$DEMO state clear

echo ""
echo "Step 1: Setup (Terminal + Shift + Session)"
$DEMO terminal register --name="POS-02"
$DEMO shift open --opening-cash=30000
$DEMO session start

echo ""
echo "Step 2: Start New Order (Draft state)"
$DEMO session new-order

echo ""
echo "Step 3: Initiate Checkout (Draft → Confirmed, Soft → Hard reservation)"
$DEMO session checkout

echo ""
echo "Step 4: Request Payment (PHP 250.00)"
$DEMO session pay --amount=25000 --method=card

echo ""
echo "Step 5: Complete Order (Inventory deducted)"
$DEMO session complete

echo ""
echo "════════════════════════════════════════════════════════════════"
echo "  Scenario 2 Complete!"
echo "════════════════════════════════════════════════════════════════"
echo ""
