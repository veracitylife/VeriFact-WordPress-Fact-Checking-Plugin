jQuery(($) => {
  const post = (action, data = {}) => $.post(verifactAdmin.ajaxurl, {action: `verifact_${action}`, nonce: verifactAdmin.nonce, ...data});
  const refreshStats = () => post('get_stats').done((response) => {
    if (!response.success) return;
    $('[data-verifact-stat="total_checks"]').text(response.data.total_checks);
  });
  $('[data-verifact-test-api]').on('click', () => {
    const button = $('[data-verifact-test-api]').prop('disabled', true);
    const result = $('[data-verifact-api-result]').text(' Checking authenticated connection...');
    post('test_api')
      .done((response) => result.text(` Connected to VeriFact API ${response.data.server_version || ''}.`))
      .fail((xhr) => result.text(` Connection failed: ${xhr.responseJSON?.data?.message || 'check the API URL, Vault key mapping, scopes, and allowed host.'}`))
      .always(() => button.prop('disabled', false));
  });
  $('[data-verifact-clear-cache]').on('click', () => post('clear_cache').done(() => window.location.reload()));
  if ($('[data-verifact-stat]').length) { refreshStats(); }
});