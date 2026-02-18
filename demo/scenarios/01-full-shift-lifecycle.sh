#!/usr/bin/env bash
set -e

DEMO="./demo/demo"

echo "════════════════════════════════════════════════════════════════"
echo "  Scenario 1: Full Shift Lifecycle"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "This scenario demonstrates a complete shift from open to close,"
echo "including terminal registration, session management, and order"
echo "processing with checkout and payment."
echo ""

# Clear previous state
$DEMO state clear

echo ""
echo "──────────────────────────────────────────────────────────────"
echo "Step 1: Register Terminal"
echo "──────────────────────────────────────────────────────────────"
$DEMO terminal register --name="POS-01-Main"

echo ""
echo "──────────────────────────────────────────────────────────────"
echo "Step 2: Open Shift (Opening Cash: PHP 500.00)"
echo "──────────────────────────────────────────────────────────────"
$DEMO shift open --opening-cash=50000 --currency=PHP

echo ""
echo "──────────────────────────────────────────────────────────────"
echo "Step 3: Start POS Session"
echo "──────────────────────────────────────────────────────────────"
$DEMO session start

echo ""
echo "──────────────────────────────────────────────────────────────"
echo "Step 4: Start New Order"
echo "──────────────────────────────────────────────────────────────"
$DEMO session new-order

echo ""
echo "──────────────────────────────────────────────────────────────"
echo "Step 5: Initiate Checkout"
echo "──────────────────────────────────────────────────────────────"
$DEMO session checkout

echo ""
echo "──────────────────────────────────────────────────────────────"
echo "Step 6: Request Payment (PHP 150.00 cash)"
echo "──────────────────────────────────────────────────────────────"
$DEMO session pay --amount=15000 --method=cash

echo ""
echo "──────────────────────────────────────────────────────────────"
echo "Step 7: Complete Order"
echo "──────────────────────────────────────────────────────────────"
$DEMO session complete

echo ""
echo "──────────────────────────────────────────────────────────────"
echo "Step 8: End POS Session"
echo "──────────────────────────────────────────────────────────────"
$DEMO session end

echo ""
echo "──────────────────────────────────────────────────────────────"
echo "Step 9: Record Cash Drop (PHP 200.00)"
echo "──────────────────────────────────────────────────────────────"
$DEMO shift cash-drop --amount=20000

echo ""
echo "──────────────────────────────────────────────────────────────"
echo "Step 10: Close Shift (Declared Cash: PHP 450.00)"
echo "──────────────────────────────────────────────────────────────"
$DEMO shift close --declared-cash=45000

echo ""
echo "════════════════════════════════════════════════════════════════"
echo "  Scenario 1 Complete!"
echo "════════════════════════════════════════════════════════════════"
echo ""
