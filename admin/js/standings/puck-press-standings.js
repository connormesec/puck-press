(function ($) {
  $(document).on('click', '#pp-refresh-standings-btn', function () {
    var $btn = $(this);
    var teamId = $btn.data('team-id') || $('#pp-active-team-id').val();
    if (!teamId) return;

    $btn.prop('disabled', true).text('Refreshing...');

    $.post(ajaxurl, {
      action: 'pp_refresh_team_standings',
      team_id: teamId
    }, function (response) {
      $btn.prop('disabled', false).text('Refresh Standings');
      if (response.success) {
        console.log('[PP Standings]', response.data.message);
        location.reload();
      } else {
        console.error('[PP Standings] Error:', response.data ? response.data.message : 'Unknown error');
        alert('Standings refresh failed: ' + (response.data ? response.data.message : 'Unknown error'));
      }
    }).fail(function () {
      $btn.prop('disabled', false).text('Refresh Standings');
      alert('Standings refresh failed: network error.');
    });
  });
})(jQuery);
