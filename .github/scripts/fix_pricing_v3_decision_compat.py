from pathlib import Path

p = Path('app/Services/RealEstateDecisionService.php')
s = p.read_text()
old = "$realisticMax = $this->number($valuation['realistic_sale_max'] ?? null);"
new = """$realisticMax = $this->number($valuation['realistic_sale_max'] ?? null)
            ?? $this->number($valuation['quick_sale_max'] ?? null)
            ?? $this->number($valuation['market_max'] ?? null);"""
if old not in s:
    raise SystemExit('decision realisticMax line not found')
s = s.replace(old, new, 1)
p.write_text(s)

Path('.github/workflows/fix-pricing-v3-decision-compat.yml').unlink(missing_ok=True)
Path('.github/scripts/fix_pricing_v3_decision_compat.py').unlink(missing_ok=True)
