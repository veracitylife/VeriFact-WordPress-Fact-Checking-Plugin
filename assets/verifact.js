(() => {
  const create = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  };

  document.querySelectorAll('[data-verifact-root]').forEach((root) => {
    const claim = create('textarea', 'vf-input');
    claim.placeholder = 'Enter a factual claim to verify';
    claim.maxLength = 10000;
    const button = create('button', 'vf-btn', 'Check facts');
    button.type = 'button';
    const status = create('div', 'vf-status');
    status.setAttribute('role', 'status');
    const output = create('div', 'vf-output');

    button.addEventListener('click', async () => {
      const value = claim.value.trim();
      if (!value) { status.textContent = 'Enter a claim first.'; return; }
      button.disabled = true;
      status.textContent = 'Checking evidence…';
      output.replaceChildren();
      try {
        const response = await fetch(VeriFactCfg.restUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {'Content-Type': 'application/json', 'X-WP-Nonce': VeriFactCfg.nonce},
          body: JSON.stringify({claim: value})
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Verification failed');
        status.textContent = `Checked in ${data.meta?.runtime_sec ?? '?'} seconds.`;
        (data.results || []).forEach((result) => {
          const card = create('article', 'vf-card');
          card.append(create('h3', 'vf-claim', result.claim || value));
          card.append(create('p', `vf-stance vf-${result.stance || 'unknown'}`, `${result.stance || 'insufficient_evidence'} · confidence ${result.confidence ?? 0}`));
          const list = create('ul', 'vf-evidence');
          (result.evidence || []).forEach((evidence) => {
            const item = create('li');
            const link = create('a', '', evidence.title || evidence.source || 'Evidence source');
            link.href = evidence.url || '#'; link.target = '_blank'; link.rel = 'noopener noreferrer';
            item.append(link, document.createTextNode(` — ${evidence.snippet || evidence.text || ''}`));
            list.append(item);
          });
          card.append(list);
          output.append(card);
        });
      } catch (error) {
        status.textContent = `Error: ${error.message}`;
      } finally { button.disabled = false; }
    });
    root.append(claim, button, status, output);
  });
})();