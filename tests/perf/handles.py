# usage: python3 handles.py <host> <out.json> [urls-file]  (link/inline handle sets of every URL, host swapped in; plain curl, no user-agent change)
import re, subprocess, sys, json, time, hashlib
# The theme registers `courtneyr-*` handles plus `cr-collection` (inc/collection.php); WordPress writes each as id="<handle>-css".
# Plugin sheets (post-kinds-indieweb-*, wp-block-*, ollie-*) never match, and a link must also load from /assets/css/.
THEME_ID = r"(?:courtneyr|cr)-[a-z0-9-]+-css"

def handle_sets(html):
    links = set()
    for tag in re.findall(r"<link\b[^>]*>", html):
        m = re.search(r"\bid=['\"](" + THEME_ID + r")['\"]", tag)
        if not m: continue
        for attr in ("href", "data-cr-href", "data-pmdelayedstyle"):
            a = re.search(r"\b" + attr + r"=['\"]([^'\"]+)['\"]", tag)
            if a and "/assets/css/" in a.group(1): links.add(f"{m.group(1)}|{attr}|{a.group(1).split('/assets/css/')[1].split('?')[0]}")
    inline = sorted(set(re.findall(r"<style\b[^>]*\bid=['\"](" + THEME_ID + r")['\"]", html)))
    return sorted(links), inline

def main():
    host, out = sys.argv[1], sys.argv[2]
    urls = sys.argv[3] if len(sys.argv) > 3 else "urls30.txt"
    res = {}
    for line in open(urls).read().splitlines():
        label, u = line.split()
        u = re.sub(r"https://[^/]+", "https://" + host, u)
        r = subprocess.run(["curl", "-s", "-w", "\n%{http_code}", u], capture_output=True)
        body, code = r.stdout.rsplit(b"\n", 1); html = body.decode("utf8", "replace")
        if code != b"200": res[label] = {"status": code.decode()}; time.sleep(1); continue
        links, inline = handle_sets(html)
        res[label] = {"status": "200", "link": links, "inline": inline, "sig": hashlib.sha256(json.dumps([links, inline]).encode()).hexdigest()[:12]}
        time.sleep(1)
    json.dump(res, open(out, "w"), indent=1)
    print(host, sum(1 for v in res.values() if v["status"] == "200"), "of", len(res), "ok")

if __name__ == "__main__": main()
