 (function(){
  const rootId = 'verifact-root';
  function h(tag, props={}, children=[]) {
    const el = document.createElement(tag);
    Object.entries(props).forEach(([k,v])=>{
      if (k === 'class') el.className = v;
      else if (k.startsWith('on') && typeof v === 'function') el.addEventListener(k.substring(2).toLowerCase(), v);
      else el.setAttribute(k, v);
    });
    (Array.isArray(children)?children:[children]).forEach(c=>{
      if (typeof c === 'string') el.appendChild(document.createTextNode(c));
      else if (c) el.appendChild(c);
    });
    return el;
  }
  function render() {
    const root = document.getElementById(rootId);
    if (!root) return;
    if (VeriFactCfg.bannerUrl) {
      const img = document.createElement('img');
      img.src = VeriFactCfg.bannerUrl;
      img.alt = 'VeriFact Banner';
      img.className = 'verifact-banner-front';
      root.appendChild(img);
    }
    const claimInput = h('textarea', { class: 'vf-input', placeholder: 'Enter a claim or leave blank and use Prompt+Answer...' });
    const promptInput = h('input', { class: 'vf-input', placeholder: 'Prompt (optional)' });
    const answerInput = h('textarea', { class: 'vf-input', placeholder: 'Answer (optional)' });
    const btn = h('button', { class: 'vf-btn' }, 'Check facts');
    const out = h('div', { class: 'vf-output' });
    btn.onclick = async () => {
      btn.disabled = true; btn.textContent = 'Checking...';
      out.innerHTML = '';
      const payload = {};
      if (claimInput.value.trim()) payload.claim = claimInput.value.trim();
      if (promptInput.value.trim()) payload.prompt = promptInput.value.trim();
      if (answerInput.value.trim()) payload.answer = answerInput.value.trim();
      if (Object.keys(payload).length === 0) {
        out.textContent = 'Provide a claim or (prompt, answer).';
        btn.disabled=false; btn.textContent='Check facts';
        return;
      }
      try {
        const res = await fetch(VeriFactCfg.restUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': VeriFactCfg.nonce },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (!res.ok) throw new Error((data && data.message) || 'Server error');
        const { results = [], meta = {} } = data;
        const metaEl = h('div', { class: 'vf-meta' }, `Checked ${meta.claims_checked || results.length} claim(s) in ${meta.runtime_sec || '?'}s`);
        out.appendChild(metaEl);
        results.forEach(r => {
          const card = h('div', { class: 'vf-card' }, [
            h('div', { class: 'vf-claim' }, `Claim: ${r.claim}`),
            h('div', { class: 'vf-stance ' + r.stance.toLowerCase().replaceAll(' ', '-') }, `Stance: ${r.stance} (conf=${r.confidence})`),
            h('div', { class: 'vf-method' }, `Method: ${r.method_summary}`),
            h('div', { class: 'vf-ev-title' }, 'Evidence:'),
            h('ul', { class: 'vf-ev' }, (r.evidence || []).map(e => h('li', {}, [
              h('a', { href: e.url || '#', target: '_blank', rel: 'noreferrer' }, e.source || 'source'),
              ' — ', h('span', {}, (e.text || '').slice(0, 240) + ((e.text || '').length > 240 ? '…' : ''))
            ]))),
            h('div', { class: 'vf-next-title' }, 'Next steps:'),
            h('ul', { class: 'vf-next' }, (r.next_steps || []).map(s => h('li', {}, s)))
          ]);
          out.appendChild(card);
        });
      } catch (e) { out.textContent = 'Error: ' + e.message; }
      finally { btn.disabled = false; btn.textContent = 'Check facts'; }
    };
    root.appendChild(h('div', { class: 'vf-card' }, [
      h('div', { class: 'vf-title' }, 'VeriFact — Fact Checker'),
      claimInput, h('div', { class: 'vf-sep' }, '— or —'), promptInput, answerInput, btn, out
    ]));
  }
  if (document.readyState !== 'loading') render();
  else document.addEventListener('DOMContentLoaded', render);
})();
