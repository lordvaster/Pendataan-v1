#!/bin/bash
# Author: Zeday @join.co.id

echo "========================================"
echo "FULL FLOW TEST - PAJAK APPLICATION"
echo "========================================"
echo ""

# Configuration
BASE_URL="http://localhost"
USERNAME="admin"
PASSWORD="Casval@2007"
COOKIE_FILE="/tmp/pajak_cookies.txt"

# Clean up old cookies
rm -f $COOKIE_FILE

echo "1. Testing Login Page..."
echo "   URL: $BASE_URL/loginpage.php"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -c $COOKIE_FILE "$BASE_URL/loginpage.php")
echo "   HTTP Code: $HTTP_CODE"
if [ "$HTTP_CODE" = "200" ]; then
    echo "   ✓ Login page accessible"
else
    echo "   ✗ Login page failed"
    exit 1
fi
echo ""

echo "2. Performing Login..."
echo "   Username: $USERNAME"
echo "   URL: $BASE_URL/login/login_process.php"

LOGIN_RESPONSE=$(curl -s -L -c $COOKIE_FILE -b $COOKIE_FILE \
    -H "Content-Type: application/json" \
    -d "{\"username\":\"$USERNAME\",\"password\":\"$PASSWORD\"}" \
    "$BASE_URL/login/login_process.php")

# Check if login successful (should redirect or return success)
if echo "$LOGIN_RESPONSE" | grep -q "dashboard\|success\|berhasil" || [ -z "$LOGIN_RESPONSE" ]; then
    echo "   ✓ Login successful"
else
    echo "   Response: $(echo "$LOGIN_RESPONSE" | head -c 200)"
    echo "   ⚠ Login response unclear, continuing..."
fi
echo ""

echo "3. Accessing Dashboard..."
echo "   URL: $BASE_URL/maindashboard.php"
DASHBOARD_CODE=$(curl -s -o /tmp/dashboard.html -w "%{http_code}" -b $COOKIE_FILE "$BASE_URL/maindashboard.php")
echo "   HTTP Code: $DASHBOARD_CODE"

if [ "$DASHBOARD_CODE" = "200" ]; then
    echo "   ✓ Dashboard accessible"
    
    # Check if dashboard contains expected elements
    if grep -q "wajib pajak\|kendaraan\|dashboard" /tmp/dashboard.html; then
        echo "   ✓ Dashboard content looks correct"
    else
        echo "   ⚠ Dashboard content may be incomplete"
    fi
elif [ "$DASHBOARD_CODE" = "302" ]; then
    echo "   ⚠ Redirected (possibly to login)"
else
    echo "   ✗ Dashboard access failed"
fi
echo ""

echo "4. Testing API tampil_data.php..."
echo "   URL: $BASE_URL/api/tampil_data.php"
API_RESPONSE=$(curl -s -b $COOKIE_FILE "$BASE_URL/api/tampil_data.php")
API_CODE=$?

if [ $API_CODE -eq 0 ]; then
    echo "   ✓ API request successful"
    
    # Parse JSON
    if echo "$API_RESPONSE" | python3 -m json.tool > /dev/null 2>&1; then
        echo "   ✓ Valid JSON response"
        
        # Count data
        DATA_COUNT=$(echo "$API_RESPONSE" | python3 -c "import sys, json; data=json.load(sys.stdin); print(len(data.get('data', [])))" 2>/dev/null)
        
        if [ ! -z "$DATA_COUNT" ]; then
            echo "   ✓ Data count: $DATA_COUNT records"
            
            if [ "$DATA_COUNT" -gt 0 ]; then
                echo "   ✓ API returning data!"
                echo ""
                echo "   Sample data (first record):"
                echo "$API_RESPONSE" | python3 -c "import sys, json; data=json.load(sys.stdin); print(json.dumps(data['data'][0] if data.get('data') else {}, indent=2))" 2>/dev/null | head -15
            else
                echo "   ✗ API returning empty data"
            fi
        fi
    else
        echo "   ✗ Invalid JSON response"
        echo "   Response: $(echo "$API_RESPONSE" | head -c 300)"
    fi
else
    echo "   ✗ API request failed"
fi
echo ""

echo "5. Checking Session..."
if [ -f $COOKIE_FILE ]; then
    echo "   ✓ Session cookie exists"
    echo "   Cookies:"
    cat $COOKIE_FILE | grep -v "^#" | awk '{print "   - " $6 "=" $7}'
else
    echo "   ✗ No session cookie"
fi
echo ""

echo "========================================"
echo "TEST SUMMARY"
echo "========================================"
echo "Login Page:    $([ "$HTTP_CODE" = "200" ] && echo "✓ OK" || echo "✗ FAIL")"
echo "Login Process: ✓ Attempted"
echo "Dashboard:     $([ "$DASHBOARD_CODE" = "200" ] && echo "✓ OK" || echo "⚠ CHECK")"
echo "API Response:  $([ ! -z "$DATA_COUNT" ] && echo "✓ OK ($DATA_COUNT records)" || echo "⚠ CHECK")"
echo "========================================"
echo ""

# Cleanup
rm -f $COOKIE_FILE /tmp/dashboard.html

echo "Note: This test was done on localhost (Apache directly)"
echo "External access via https://yourdomain.com"
echo "may have different results due to reverse proxy configuration."
