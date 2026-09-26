"""Consulta GitHub usando el administrador de credenciales existente, sin imprimir secretos."""
import io, json, os, re, subprocess, sys, urllib.request, urllib.error, zipfile


class SafeRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        redirected = super().redirect_request(req, fp, code, msg, headers, newurl)
        if redirected:
            redirected.remove_header("Authorization")
        return redirected


opener = urllib.request.build_opener(SafeRedirect())
env = dict(os.environ, GIT_TERMINAL_PROMPT="0", GCM_INTERACTIVE="never")
result = subprocess.run(["git", "credential", "fill"], input="protocol=https\nhost=github.com\npath=AquinoB2090/Api_Parcial2.git\n\n", text=True, capture_output=True, env=env)
credentials = dict(line.split("=", 1) for line in result.stdout.splitlines() if "=" in line)
headers = {"Accept": "application/vnd.github+json", "User-Agent": "ApiParcial-verification", "X-GitHub-Api-Version": "2022-11-28"}
if credentials.get("password"):
    headers["Authorization"] = "Bearer " + credentials["password"]
path = sys.argv[1] if len(sys.argv) > 1 else "/actions/runs?per_page=3"
assert path.startswith("/") and not path.startswith("//")
url = "https://api.github.com/repos/AquinoB2090/Api_Parcial2" + path
try:
    with opener.open(urllib.request.Request(url, headers=headers), timeout=30) as response:
        raw = response.read()
    if path.endswith('/logs'):
        logs = zipfile.ZipFile(io.BytesIO(raw))
        for name in logs.namelist():
            content = logs.read(name).decode('utf-8', errors='replace')
            hosts = sorted(set(re.findall(r'https://[a-zA-Z0-9.-]+\.azurewebsites\.net', content)))
            errors = [line for line in content.splitlines() if '##[error]' in line]
            if hosts or errors:
                print(json.dumps({'file': name, 'hosts': hosts, 'errors': errors[:20]}, ensure_ascii=False))
        sys.exit(0)
    payload = json.loads(raw)
    if "workflow_runs" in payload:
        print(json.dumps([{"id": x["id"], "status": x["status"], "conclusion": x["conclusion"], "sha": x["head_sha"], "url": x["html_url"]} for x in payload["workflow_runs"]], indent=2))
    elif "jobs" in payload:
        print(json.dumps([{"id": x['id'], "name": x["name"], "status": x["status"], "conclusion": x["conclusion"], "steps": [{"name": s["name"], "status": s["status"], "conclusion": s["conclusion"]} for s in x["steps"]]} for x in payload["jobs"]], indent=2))
    elif isinstance(payload, list):
        print(json.dumps([{"id": x.get("id"), "state": x.get("state"), "environment_url": x.get("environment_url"), "url": x.get("url")} for x in payload], indent=2))
    else:
        print(json.dumps({k: payload.get(k) for k in ["id","status","conclusion","html_url","default_branch","visibility"]}, indent=2))
except urllib.error.HTTPError as error:
    print("GitHub HTTP", error.code)
    sys.exit(1)
