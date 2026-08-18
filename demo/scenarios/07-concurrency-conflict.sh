#!/usr/bin/env bash
set -e

DEMO="./demo/demo"

echo "════════════════════════════════════════════════════════════════"
echo "  Scenario 7: Concurrency Conflict Detection"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "Demonstrates optimistic locking and concurrency conflict detection"
echo "The demo persists events to a file, so real concurrency IS reachable:"
echo "every command is its own process, and the event store refuses a second"
echo "event claiming a version that is already taken."
echo ""
echo "To see a real conflict, run two commands against one shift at once:"
echo "  ./demo shift cash-drop --shift-id=<id> --amount=1000 &"
echo "  ./demo shift cash-drop --shift-id=<id> --amount=2000 &"
echo "One succeeds; the other reports a concurrency conflict and changes"
echo "nothing. (tests/Integration/DemoCliShiftOpenRaceTest.php automates the"
echo "same idea for two concurrent shift opens.)"
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
echo "FileEventStore checks, inside its write lock and against the current"
echo "file, that the version an event claims is still free — so the losing"
echo "process gets a ConcurrencyException instead of corrupting the stream."
echo ""
