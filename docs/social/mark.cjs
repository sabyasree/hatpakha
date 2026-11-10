// The hatpakha "p" mark, redrawn as SVG from public/images/logo.png (viewBox 0 0 640 700).
module.exports = function mark() {
  const cx = 343, cy = 265, R = 212, F = [cx - R, cy];
  const ray = (a1, a2, r, fill) => {
    const p = a => `${(F[0] + r * Math.cos(a)).toFixed(1)} ${(F[1] + r * Math.sin(a)).toFixed(1)}`;
    return `<path d="M${F[0]} ${F[1]} L${p(a1)} L${p(a2)} Z" fill="${fill}"/>`;
  };
  let reds = '', peach = '';
  const n = 34, span = Math.PI * 1.02;
  for (let k = 0; k < n; k++) {
    const a1 = -span / 2 + span * k / n, a2 = -span / 2 + span * (k + 1) / n;
    reds += ray(a1, a2, 500, k % 2 ? '#F08D90' : '#CB1B32');
    peach += ray(a1, a2, 500, k % 2 ? '#FBEBD8' : '#EFC08D');
  }
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 700">
  <defs><clipPath id="fan"><circle cx="${cx}" cy="${cy}" r="${R}"/></clipPath>
  <clipPath id="lens"><circle cx="${F[0]}" cy="${F[1]}" r="${R * 0.82}"/></clipPath></defs>
  <circle cx="327" cy="265" r="219" fill="#2DAAE2"/>
  <rect x="75" y="42" width="62" height="628" fill="#2DAAE2"/>
  <g clip-path="url(#fan)">${reds}<g clip-path="url(#lens)">${peach}</g></g>
</svg>`;
};
