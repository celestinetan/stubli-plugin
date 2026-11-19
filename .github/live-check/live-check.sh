#!/bin/bash
# "Finished?" check: does the live world match what's released?
# Runs in the PUBLIC repo celestinetan/stubli-plugin (moved from the private repo 19 Nov 2026, so it runs
# free), from the repo's root. Changes nothing anywhere. Prints PASS/FAIL/SKIP per line.
#
# What it needs, and where it comes from:
# - the released plugin version: wordgram.php in this public repo;
# - the theme version and "Where things are": only in the private repo celestinetan/stubli, read with
#   STUBLI_PRIVATE_READ (a read-only token Celestine saved as a secret). Without it those lines say SKIP;
# - the WordPress.com test site's address: the secret LIVE_TEST_SITE, so it never shows in public logs.
# REPORT_ONLY=1 (the default until the private repo's Actions run again): problems are reported as
# warnings and the run stays green. REPORT_ONLY=0 makes the run fail when something is out of step.
set -u
pass=0; fail=0; skip=0
REPORT_ONLY="${REPORT_ONLY:-1}"
ok() {
  if [ "$1" = 1 ]; then echo "PASS  $2"; pass=$((pass+1)); else echo "FAIL  $2"; fail=$((fail+1))
    if [ -n "${GITHUB_ACTIONS:-}" ]; then [ "$REPORT_ONLY" = 1 ] && echo "::warning title=Out of step::$2" || echo "::error title=Out of step::$2"; fi
  fi
}
skipped() { echo "SKIP  $1"; skip=$((skip+1)); }
get() {
  # api.github.com refuses unsigned calls once the shared runner's hourly limit is used up (8–9 Nov).
  if [[ "$1" == https://api.github.com/* && -n "${GITHUB_TOKEN:-}" ]]; then
    curl -sL --max-time 20 -H 'Cache-Control: no-cache' -H "Authorization: Bearer $GITHUB_TOKEN" "$1"
  else
    curl -sL --max-time 20 -H 'Cache-Control: no-cache' "$1"
  fi
}
private() { # a file from the private repo, read-only; empty without the token
  [ -z "${STUBLI_PRIVATE_READ:-}" ] && return 0
  local p; p=$(python3 -c "import sys,urllib.parse; print(urllib.parse.quote(sys.argv[1]))" "$1")
  curl -sfL --max-time 20 -H "Authorization: Bearer $STUBLI_PRIVATE_READ" -H 'Accept: application/vnd.github.raw+json' \
    "https://api.github.com/repos/celestinetan/stubli/contents/$p?ref=main"
}

P=$(grep -m1 -oP "^\s*\*\s*Version:\s*\K[0-9.]+" wordgram.php)
THEME=$(private "theme/style.css"); T=$(echo "$THEME" | grep -m1 -oP "^Version:\s*\K[0-9.]+")
PRIVP=$(private "plugin/wordgram.php" | grep -m1 -oP "^\s*\*\s*Version:\s*\K[0-9.]+")
echo "Released plugin $P; private repo: plugin ${PRIVP:-(not readable)}, theme ${T:-(not readable)}"

# WordPress.org
WPORG=$(get "https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&slug=stubli&fields=sections")
WV=$(python3 -c "import json,sys; print(json.loads(sys.argv[1]).get('version',''))" "$WPORG" 2>/dev/null)
ok "$([ "$WV" = "$P" ] && echo 1)" "WordPress.org offers $P (it offers ${WV:-nothing})"
CL=$(python3 -c "import json,sys; print(json.loads(sys.argv[1]).get('sections',{}).get('changelog',''))" "$WPORG" 2>/dev/null)
ok "$([[ "$CL" == *"$P"* ]] && echo 1)" "WordPress.org's changelog has an entry for $P"
ok "$([[ "$CL" != *'&amp;#'* && "$CL" != *'&amp;amp;'* ]] && echo 1)" "WordPress.org's changelog shows no stray codes like &#039;"

# GitHub
REL=$(get "https://api.github.com/repos/celestinetan/stubli-plugin/releases/latest" | python3 -c "import json,sys; print(json.load(sys.stdin).get('tag_name',''))" 2>/dev/null)
ok "$([ "$REL" = "v$P" ] && echo 1)" "Latest GitHub Release is v$P (it is ${REL:-none})"
if [ -n "$PRIVP" ]; then ok "$([ "$PRIVP" = "$P" ] && echo 1)" "The private repo's plugin is the released one, $P (it is $PRIVP)"
else skipped "The private repo's plugin version (needs the STUBLI_PRIVATE_READ secret)"; fi

# Live sites: the plugin reports its version in /wp-json/stubli/v1/profile ("generator": "stubli/x.y.z").
site_check() { # host, label
  local G; G=$(get "https://$1/wp-json/stubli/v1/profile?nocache=$RANDOM" | python3 -c "import json,sys; print(json.load(sys.stdin).get('generator','').replace('stubli/',''))" 2>/dev/null)
  ok "$([ "$G" = "$P" ] && echo 1)" "$2 runs plugin $P (it runs ${G:-unknown}; with auto-updates this can lag a few hours)"
}
site_check stubli.app stubli.app
if [ -n "${LIVE_TEST_SITE:-}" ]; then site_check "$LIVE_TEST_SITE" "The WordPress.com test site"
else skipped "The WordPress.com test site (needs the LIVE_TEST_SITE secret)"; fi
LT=$(get "https://stubli.app/wp-content/themes/stubli/style.css?nocache=$RANDOM" | grep -m1 -oP "^Version:\s*\K[0-9.]+")
if [ -n "$T" ]; then ok "$([ "$LT" = "$T" ] && echo 1)" "stubli.app runs theme $T (it runs ${LT:-unknown}; the theme is uploaded by hand)"
else skipped "stubli.app's theme against the private repo (needs STUBLI_PRIVATE_READ); stubli.app runs ${LT:-unknown}"; fi

# stubli.app stays verified with Google and Bing (Celestine, 2 Nov: "how can we ensure this is not forgotten again?").
HOME_HTML=$(get "https://stubli.app/?nocache=$RANDOM")
ok "$([[ "$HOME_HTML" == *'name="google-site-verification"'* ]] && echo 1)" "stubli.app keeps its Google Search Console tag (Jetpack → SEO → Site verification)"
ok "$([[ "$HOME_HTML" == *'name="msvalidate.01"'* ]] && echo 1)" "stubli.app keeps its Bing Webmaster Tools tag (theme inc/hub.php)"
ROBOTS=$(get "https://stubli.app/robots.txt")
ok "$([[ "$ROBOTS" == *'Sitemap: https://stubli.app/sitemap.xml'* && "$ROBOTS" != *'Disallow: /'$'\n'* ]] && echo 1)" "stubli.app's robots.txt lets search engines and AI in, and names the sitemap"
# Every page in the sitemap opens (a warning only: WordPress.com lists posts in the bin until it's emptied).
HERE=$(cd "$(dirname "$0")" && pwd)
SM=$(python3 "$HERE/sitemap-check.py" https://stubli.app/sitemap.xml)
SMBAD=$(echo "$SM" | grep '^BAD' | head -10 | sed 's/^BAD //' | paste -sd ';' - | sed 's/;/; /g')
if [[ "$SM" == OK* ]]; then echo "PASS  Every page in stubli.app's sitemap opens (${SM/OK /all } pages)"
else echo "WARN  Pages in stubli.app's sitemap that don't open: ${SMBAD:-no answer}"
  [ -n "${GITHUB_ACTIONS:-}" ] && echo "::warning title=Sitemap::Pages in stubli.app's sitemap that don't open: ${SMBAD:-no answer}"; fi

# The project's own record (private repo).
WT=$(private "docs/Where things are.md")
if [ -n "$WT" ]; then
  ok "$(grep -q "\*\*$P\*\*" <<<"$WT" && echo 1)" "\"Where things are\" names plugin $P"
  [ -n "$T" ] && ok "$(grep -q "\*\*$T\*\*" <<<"$WT" && echo 1)" "\"Where things are\" names theme $T"
else skipped "\"Where things are\" names the versions (needs STUBLI_PRIVATE_READ)"; fi

echo "live-check: $pass passed, $fail failed, $skip skipped"
if [ -n "${GITHUB_ACTIONS:-}" ]; then
  echo "::notice title=Live check::plugin $P, theme ${T:-?}: $pass passed, $fail failed, $skip skipped$([ "$REPORT_ONLY" = 1 ] && echo ' (report only)')"
  { echo "### Live check"; echo; echo "Plugin $P, theme ${T:-?}: **$pass passed, $fail failed, $skip skipped**$([ "$REPORT_ONLY" = 1 ] && echo ' (report only: the run stays green)')"; } >> "${GITHUB_STEP_SUMMARY:-/dev/null}"
fi
[ "$REPORT_ONLY" = 1 ] || [ "$fail" = 0 ]
