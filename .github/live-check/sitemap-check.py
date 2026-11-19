#!/usr/bin/env python3
"""Does every page stubli.app's sitemap lists actually open?

Google Search Console said on 9 Nov 2026 that some pages in stubli.app's sitemap were
"Not found (404)" without naming them. This reads the sitemap (and the sitemaps it points to),
opens every page address in it and prints the ones that don't answer 200.

Usage:  python3 tests/sitemap-check.py https://stubli.app/sitemap.xml
        python3 tests/sitemap-check.py --parse <file.xml>    (prints the <loc> addresses; for the self-test)
Prints "OK <n>" when all n pages open, else one "BAD <status> <url>" line per page. Exit 1 if any are bad.
Read-only: it only reads pages.
"""
import sys
import urllib.request
import urllib.error
import xml.etree.ElementTree as ET

NS = '{http://www.sitemaps.org/schemas/sitemap/0.9}'
UA = 'Mozilla/5.0 (compatible; StubliLiveCheck/1.0; +https://github.com/celestinetan/stubli-plugin)'
MAX_PAGES = 400


def parse(xml_bytes):
    """Returns (child sitemaps, page addresses) from one sitemap file.
    Only <loc> directly under <sitemap> or <url> counts (not image:loc or video:loc)."""
    root = ET.fromstring(xml_bytes)
    maps = [e.findtext(NS + 'loc', '').strip() for e in root.findall(NS + 'sitemap')]
    pages = [e.findtext(NS + 'loc', '').strip() for e in root.findall(NS + 'url')]
    return [m for m in maps if m], [p for p in pages if p]


def fetch(url, method='GET'):
    req = urllib.request.Request(url, method=method, headers={'User-Agent': UA, 'Cache-Control': 'no-cache'})
    try:
        with urllib.request.urlopen(req, timeout=20) as r:
            return r.status, (r.read() if method == 'GET' else b'')
    except urllib.error.HTTPError as e:
        return e.code, b''
    except Exception as e:  # timeouts, DNS
        return 'error: ' + type(e).__name__, b''


def main():
    if len(sys.argv) == 3 and sys.argv[1] == '--parse':
        with open(sys.argv[2], 'rb') as f:
            maps, pages = parse(f.read())
        for u in maps + pages:
            print(u)
        return 0
    todo, seen, pages, bad = [sys.argv[1]], set(), [], 0
    while todo:
        sm = todo.pop(0)
        if sm in seen:
            continue
        seen.add(sm)
        status, body = fetch(sm)
        if status != 200:
            bad += 1
            print('BAD %s %s (a sitemap)' % (status, sm))
            continue
        maps, found = parse(body)
        todo += maps
        pages += [p for p in found if p not in pages]
    for p in pages[:MAX_PAGES]:
        status, _ = fetch(p)
        if status != 200:
            bad += 1
            print('BAD %s %s' % (status, p))
    if not bad:
        print('OK %d' % len(pages[:MAX_PAGES]))
    return 1 if bad else 0


if __name__ == '__main__':
    sys.exit(main())
