# Verifying Whether BreezeDoc Supports Raw PDF Download

A runbook for re-checking the recurring question: **"Can we get the actual PDF file of a
document from the BreezeDoc API yet?"**

**Answer as of the last check (2026-07-23): No.** The API exposes only per-page JPEG images
via pre-signed S3 URLs (`document_files[].document_file_pages[].url`), which the SDK already
wraps in `Documents::downloadPageImages()`. There is no endpoint or field for the original or
signed PDF.

Re-run the steps below to confirm the answer hasn't changed. It takes ~2 minutes.

## Prerequisites

- A valid Personal Access Token. The repo keeps one in `.env` as `BREEZEDOC_PAT`
  (`BREEZEDOC_PAT_TEST` also works). Load it:

  ```bash
  set -a; source .env; set +a
  TOKEN="${BREEZEDOC_PAT:-$BREEZEDOC_PAT_TEST}"
  BASE="https://breezedoc.com/api"
  ```

- **Send a browser `User-Agent`.** BreezeDoc sits behind Cloudflare, which 403s the default
  `curl`/`python-urllib`/WebFetch user agents. Add `-A "Mozilla/5.0"` to every `curl`, or a
  `User-Agent: Mozilla/5.0` header in code. (Plain `curl` with no `-A` happens to work; the
  point is: an unexpected 403 usually means UA blocking, not an auth/endpoint problem.)

## Step 1 — Confirm the token works

```bash
curl -s -A "Mozilla/5.0" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  "$BASE/me"
```

Expect `200` with your user JSON. A 401/403 here means the token expired (tokens last ~1 year)
— regenerate at https://breezedoc.com/integrations/api before continuing.

## Step 2 — Inspect a *completed* document's response for any PDF/file field

The key test: a fully signed document is where a "download the signed PDF" field would appear
if one existed. List documents, pick a completed one, fetch its detail, and scan every key.

```bash
python3 - "$TOKEN" <<'PY'
import json, sys, urllib.request
token = sys.argv[1]; base = "https://breezedoc.com/api"
H = {"Authorization": "Bearer "+token, "Accept": "application/json", "User-Agent": "Mozilla/5.0"}
get = lambda p: json.load(urllib.request.urlopen(urllib.request.Request(base+p, headers=H)))

docs = get("/documents")["data"]
completed = [d for d in docs if d.get("completed_at")]
target = (completed or docs)[0]
full = get("/documents/%d" % target["id"])
print("doc", target["id"], "completed_at =", full.get("completed_at"))
print("top-level keys:", sorted(full.keys()))

hits = []
def walk(o, p=""):
    if isinstance(o, dict):
        for k, v in o.items():
            if any(t in k.lower() for t in ("pdf", "download", "file", "url")):
                hits.append((p+"/"+k, v if not isinstance(v, (dict, list)) else "<%s>" % type(v).__name__))
            walk(v, p+"/"+k)
    elif isinstance(o, list):
        for i, v in enumerate(o[:2]): walk(v, p+"/[%d]" % i)
walk(full)
print("\nkeys matching pdf/download/file/url:")
for path, val in hits: print(" ", path, "=>", str(val)[:100])
PY
```

**Pass condition (still no PDF):** the only file-bearing key is
`/document_files/[..]/document_file_pages/[..]/url`, and the URL ends in `.pdf-<n>.jpg` (a
per-page JPEG). There is **no** top-level or nested `pdf` / `download` / raw `file` field.

**If you see a new field** (e.g. `pdf_url`, `download_url`, a `file` with a `.pdf` URL) → the
API changed; the answer is now "yes." Update the SDK and this doc accordingly (and see
["What 'yes, it's supported now' would look like"](#what-yes-its-supported-now-would-look-like)
below).

## Step 3 — Probe the obvious endpoints (expect all 404)

```bash
ID=<completed-doc-id-from-step-2>
for p in download pdf file files download-pdf signed; do
  code=$(curl -s -o /dev/null -w "%{http_code}" -A "Mozilla/5.0" \
    -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
    "$BASE/documents/$ID/$p")
  echo "$code  GET /documents/$ID/$p"
done
```

All `404` → no download endpoint exists. Any `200` → investigate that endpoint.

## Step 4 — Confirm there's no S3 back door to the source PDF

The page-image URLs look like `.../<hash>.pdf-0.jpg`, i.e. derivatives of a source
`<hash>.pdf` object. Tempting to strip the `-0.jpg` suffix and fetch the original — but the
pre-signed signature is scoped to the exact derivative key, so this fails:

```bash
# PAGEURL = a document_file_pages[].url from step 2 (they expire, so grab a fresh one)
BASEPDF=$(python3 -c "import sys,re; from urllib.parse import urlsplit,urlunsplit; \
s=urlsplit(sys.argv[1]); \
print(urlunsplit((s.scheme,s.netloc,re.sub(r'\.pdf-\d+\.jpg$','.pdf',s.path),s.query,'')))" "$PAGEURL")
curl -s "$BASEPDF" | head -c 200
```

Expect S3 `<Code>SignatureDoesNotMatch</Code>` (403). This confirms there's no way to reach the
original PDF by URL manipulation. (For comparison, the unmodified page URL returns `200` with
`Content-Type: image/jpeg`.)

## Step 5 — Cross-check the live docs' endpoint list

The published docs are a Redoc bundle; the operation list is embedded in the page HTML:

```bash
curl -s -A "Mozilla/5.0" https://breezedoc.com/developer/docs/ \
  | grep -oiE "/(documents|templates|invoices|recipients|teams|me)[a-z0-9{}/_-]*" | sort -u
```

Under Documents you should see only: `listDocuments`, `getDocument`, `storeDocument`,
`sendDocument`, `listDocumentRecipients`, `listTeamDocuments`. A new `download`/`pdf` operation
appearing here is the strongest signal the answer changed.

> Note: `WebFetch`/browserless fetches of `breezedoc.com/developer/docs/` and
> `.../openapi.json` return **403** (Cloudflare) — that's a UA block, not evidence the docs
> moved. Use `curl -A "Mozilla/5.0"`.

## What "yes, it's supported now" would look like

Any one of: a `*_pdf`/`download*` field in the Step 2 response, a non-404 in Step 3, or a new
download operation in Step 5. If that happens, the follow-up work is a `Documents::downloadPdf()`
(and `downloadPdfTo()`) method mirroring the existing `downloadPageImages()` pair.

## Why this keeps coming up

Without a PDF endpoint, the only way to produce a PDF is to fetch the page JPEGs (Step 2 URLs,
already wrapped by the SDK) and stitch them client-side — an image-only PDF with no selectable
text or vector layer. That limitation is why this gets re-checked periodically.
