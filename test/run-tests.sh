#!/bin/bash
# Run all Mapper module tests by directory to avoid test isolation issues.
#
# Usage: ./run-tests.sh [--verbose]

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
OMEKA_DIR="$(cd "$SCRIPT_DIR/../../.." && pwd)"
PHPUNIT="$OMEKA_DIR/vendor/bin/phpunit"

cd "$OMEKA_DIR"

VERBOSE=""
if [[ "$1" == "--verbose" ]]; then
    VERBOSE="--testdox"
fi

FAILED=0

echo "========================================="
echo "Running Mapper Module Tests"
echo "========================================="

echo ""
echo "--- Stdlib Tests ---"
$PHPUNIT -c modules/Mapper/test/phpunit.xml modules/Mapper/test/MapperTest/Stdlib $VERBOSE || FAILED=1

echo ""
echo "--- Service Tests ---"
$PHPUNIT -c modules/Mapper/test/phpunit.xml modules/Mapper/test/MapperTest/Service $VERBOSE || FAILED=1

echo ""
echo "--- Api Tests ---"
$PHPUNIT -c modules/Mapper/test/phpunit.xml modules/Mapper/test/MapperTest/Api $VERBOSE || FAILED=1

echo ""
echo "--- Controller Tests ---"
$PHPUNIT -c modules/Mapper/test/phpunit.xml modules/Mapper/test/MapperTest/Controller $VERBOSE || FAILED=1

echo ""
echo "========================================="
if [[ $FAILED -eq 0 ]]; then
    echo "All tests passed!"
else
    echo "Some tests failed!"
    exit 1
fi
