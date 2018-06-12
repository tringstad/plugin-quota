rcmail.addEventListener('init', function(evt) {
  var tab = $('<span>')
    .attr('id', 'settingstabpluginquota')
    .addClass('tablink');

  var button = $('<a>')
    .attr('href', rcmail.env.comm_path + '&_action=plugin.quota')
    .html(rcmail.gettext('quota_plugin_title', 'quota'))
    .appendTo(tab);

  rcmail.add_element(tab, 'tabs');
});
