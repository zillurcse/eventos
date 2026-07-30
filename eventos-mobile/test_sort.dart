void main() {
  var tabs = [
    {'name': 'RECEPTION', 'order': 1},
    {'name': 'EVENT FEED', 'order': 2},
  ];
  tabs.sort((a, b) => (a['order'] as int).compareTo(b['order'] as int));
  print(tabs);
}
