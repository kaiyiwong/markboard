#!/usr/bin/env python3
"""Save each page's rendered HTML from the running app, with its CSS and fonts inlined, so the
design checks can run on a single file with no server: text contrast in light and dark, sideways
scroll at 320 to 1440, and text spacing (ui-foundations' check_page.py).

Usage (with the app running on the demo hub):
  npm run snapshot -- [--base-url http://localhost] [--out storage/app/snapshots]
  python3 <ui-foundations>/scripts/check_page.py storage/app/snapshots/projects.html

Needs Python Playwright with Chromium, as check_page.py does; set PYTHON to the interpreter that
has it if that isn't python3 (PYTHON=python3.10 npm run snapshot). Each snapshot drops the page's
scripts: it is the page as rendered, not a working app.
"""
import argparse, asyncio, base64, pathlib, re, sys
from urllib.parse import urljoin

try:
    from playwright.async_api import async_playwright
except ImportError:
    sys.exit(f'Python Playwright is not installed for {sys.executable}. Install it (pip install playwright, then '
             'playwright install chromium), or set PYTHON to an interpreter that has it.')

# Each page component in a state worth checking: the demo hub has an overdue project, a file with
# format errors, a project with no TASKS.md, a pipeline with every open stage and briefs.
PAGES = {
    'projects': '/',
    'projects-search': '/?q=spreadsheet',
    'project': '/projects/lantern',
    'project-task': '/projects/bramble-bakery/tasks/T4',
    'project-errors': '/projects/muse-lab',
    'project-no-tasks': '/projects/reading-list',
    'pipeline': '/pipeline',
    'brief': '/brief',
    'brief-past': '/brief/2026-10-05',
}

COLLECT_CSS = """() => [...document.styleSheets].map((sheet) => ({
    href: sheet.href,
    css: [...sheet.cssRules].map((rule) => rule.cssText).join('\\n'),
}))"""


async def inline_urls(request, css: str, base: str) -> str:
    """Replaces every url(...) in the CSS (fonts, mostly) with a data URI."""
    for ref in set(re.findall(r'url\(["\']?([^"\')]+)["\']?\)', css)):
        if ref.startswith('data:'):
            continue
        response = await request.get(urljoin(base, ref))
        if not response.ok:
            raise RuntimeError(f'{urljoin(base, ref)} returned {response.status}')
        kind = response.headers.get('content-type', 'application/octet-stream').split(';')[0]
        data = base64.b64encode(await response.body()).decode()
        css = re.sub(r'url\(["\']?' + re.escape(ref) + r'["\']?\)', f'url("data:{kind};base64,{data}")', css)
    return css


async def snapshot(page, base_url: str, path: str) -> str:
    response = await page.goto(urljoin(base_url, path), wait_until='networkidle')
    if response is None or not response.ok:
        raise RuntimeError(f'{path} returned {response.status if response else "nothing"}')
    await page.wait_for_selector('#main')
    sheets = await page.evaluate(COLLECT_CSS)
    css = '\n'.join([await inline_urls(page.request, sheet['css'], sheet['href'] or page.url) for sheet in sheets])
    html = await page.evaluate('document.documentElement.outerHTML')
    html = re.sub(r'<script\b[^>]*>.*?</script>', '', html, flags=re.S)
    html = re.sub(r'<link\b[^>]*rel="(?:stylesheet|modulepreload|preload)"[^>]*>', '', html)
    html = re.sub(r'<style\b[^>]*>.*?</style>', '', html, flags=re.S)
    return '<!DOCTYPE html>\n' + html.replace('</head>', f'<style>\n{css}\n</style>\n</head>', 1)


async def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument('--base-url', default='http://localhost')
    parser.add_argument('--out', default='storage/app/snapshots')
    args = parser.parse_args()
    out = pathlib.Path(args.out)
    out.mkdir(parents=True, exist_ok=True)

    async with async_playwright() as playwright:
        browser = await playwright.chromium.launch()
        page = await browser.new_page(viewport={'width': 1440, 'height': 900})
        for name, path in PAGES.items():
            file = out / f'{name}.html'
            file.write_text(await snapshot(page, args.base_url, path))
            print(f'saved {file}  ({path})')
        await browser.close()
    return 0


if __name__ == '__main__':
    sys.exit(asyncio.run(main()))
