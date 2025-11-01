from flask import Flask, request, jsonify, render_template
import requests, re, random, threading, os

app = Flask(__name__)

# --- Random FB mobile user agents for realism ---
UA_LIST = [
    "Mozilla/5.0 (Linux; Android 10; Wildfire E Lite) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/105.0.5195.136 Mobile Safari/537.36 [FBAN/EMA;FBLC/en_US;FBAV/298.0.0.10.115;]",
    "Mozilla/5.0 (Linux; Android 11; KINGKONG 5 Pro) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/87.0.4280.141 Mobile Safari/537.36 [FBAN/EMA;FBLC/fr_FR;FBAV/320.0.0.12.108;]",
    "Mozilla/5.0 (Linux; Android 11; G91 Pro) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/106.0.5249.126 Mobile Safari/537.36 [FBAN/EMA;FBLC/fr_FR;FBAV/325.0.1.4.108;]"
]


# --- Extract access token from cookie ---
def extract_token(cookie, ua):
    try:
        cookies = {i.split('=')[0]: i.split('=')[1] for i in cookie.split('; ') if '=' in i}

        # Try Facebook business endpoint
        res = requests.get(
            "https://business.facebook.com/business_locations",
            headers={"user-agent": ua, "referer": "https://www.facebook.com/"},
            cookies=cookies,
            timeout=10,
        )
        token_match = re.search(r'(EAAG\w+)', res.text)
        if token_match:
            return token_match.group(1)

        # Fallback public API
        api_res = requests.post("https://c2t.lara.rest/", json={"cookie": cookie}, timeout=10)
        if api_res.status_code == 200:
            data = api_res.json()
            return data.get("access_token")
    except Exception as e:
        print("Token extraction error:", e)
    return None


# --- Function that performs a single share ---
def share_once(cookie, post_link, ua, results):
    try:
        token = extract_token(cookie, ua)
        if not token:
            results.append({"cookie": cookie[:20], "status": False, "message": "Invalid or suspended cookie"})
            return

        cookies = {i.split('=')[0]: i.split('=')[1] for i in cookie.split('; ') if '=' in i}

        res = requests.post(
            "https://graph.facebook.com/v18.0/me/feed",
            params={"link": post_link, "access_token": token, "published": 0},
            headers={"user-agent": ua},
            cookies=cookies,
            timeout=10,
        )

        if "id" in res.text:
            results.append({"cookie": cookie[:20], "status": True})
        else:
            results.append({"cookie": cookie[:20], "status": False})
    except Exception as e:
        results.append({"cookie": cookie[:20], "status": False, "message": str(e)})


@app.route("/")
def index():
    return render_template("index.html")


@app.route("/api/share", methods=["POST"])
def share():
    data = request.get_json()
    cookies_input = data.get("cookie")
    post_link = data.get("link")
    limit = int(data.get("limit", 0))

    if not cookies_input or not post_link or not limit:
        return jsonify({"status": False, "message": "⚠️ Please fill all fields."})

    # Multiple cookies allowed (split by newline or comma)
    cookie_list = [c.strip() for c in re.split(r'[\n,]+', cookies_input) if c.strip()]
    if not cookie_list:
        return jsonify({"status": False, "message": "⚠️ No valid cookies provided."})

    ua = random.choice(UA_LIST)
    results = []
    threads = []

    # --- Run shares concurrently for speed ---
    for i in range(limit):
        cookie = random.choice(cookie_list)
        t = threading.Thread(target=share_once, args=(cookie, post_link, ua, results))
        t.start()
        threads.append(t)

    for t in threads:
        t.join()

    success = len([r for r in results if r["status"]])
    failed = len(results) - success

    return jsonify({
        "status": True,
        "message": f"✅ Shared successfully {success} times. ❌ Failed: {failed}.",
        "success_count": success,
        "failed_count": failed
    })


if __name__ == "__main__":
    port = int(os.environ.get("PORT", 5000))
    app.run(debug=False, host="0.0.0.0", port=port)
