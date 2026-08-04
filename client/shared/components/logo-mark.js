/**
 * Small brand mark shown next to the page title on every top-level admin
 * page. Purely decorative (aria-hidden) — plain CSS, no image asset to
 * ship or keep in sync with a real logo later.
 */
export function LogoMark() {
	return (
		<span className="zoviz-logo" aria-hidden="true">
			Z
		</span>
	);
}
