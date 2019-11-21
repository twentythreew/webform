function removeFiles(opts) {
  var folder = DriveApp.getFolderById(opts.folderId)
  var iterator = folder.searchFiles('title contains "'+opts.title+'"')

  while (iterator.hasNext()) {
    var file = iterator.next().setTrashed(true) //Prevent printing an old list that Cindy pended and shipped on her own
    infoEmail('removeFiles', file.getUrl(), file.getName(), opts)
  }
}

function testWatch() {
  var folder = DriveApp.getFoldersByName('OLD').next()
  var query  = 'modifiedDate > "2019-11-19T16:07:49.089Z"'
  var iterator = folder.searchFiles(query)

  Logger.log(['testWatch', query, iterator.hasNext() ? iterator.next().getUrl() : 'No Files Modified'])
}

function watchFiles(opts) {

  var today     = new Date();
  var minutes   = opts.minutes || 5
  var oneDayAgo = new Date(today.getTime() - minutes * 60 * 60 * 1000);
  var startTime = oneDayAgo.toISOString();

  var files    = []
  var folder   = DriveApp.getFoldersByName(opts.folder).next()
  var iterator = folder.searchFiles('modifiedDate > "' + startTime + '"')

  while (iterator.hasNext()) {

    var next = iterator.next()
    var file = {
      name:next.getName(),
      id:next.getId(),
      url:next.getUrl(),
      date_modified:next.getLastUpdated(),
      date_created:next.getDateCreated()
    }

    file.isNew = (file.date_modified - file.date_created) < 1 * 60 * 1000 //1 minute

    if ( ! opts.includeNew && file.isNew) continue;

    //getBody does not have headers or footers
    var doc = DocumentApp.openById(next.getId())
    var documentElement = doc.getBody().getParent()
    var numChildren = documentElement.getNumChildren()

    for (var i = 0; i<numChildren; i++) {
      var child = documentElement.getChild(i)
      file['part'+i] = child.getText()
    }

    files.push(file)
  }

  infoEmail('watchFiles', folder, files)
  return files
}

function newSpreadsheet(opts) {

  var ss   = SpreadsheetApp.create(opts.file)
  var file = DriveApp.getFileById(ss.getId())

  if (opts.vals) {
    ss.getRange(1, 1, opts.vals.length, opts.vals[0].length).setValues(opts.vals).setHorizontalAlignment('left').setFontFamily('Roboto Mono')
  }

  var widths = opts.widths || {}
  for (var col in widths) {
    ss.setColumnWidth(col, widths[col]); //show the full id when it print
  }

  moveToFolder(file, opts.folder)
}

function moveToFolder(file, folder) {
  if ( ! folder ) return
  parentByFile(file).removeFile(file)
  folderByName(folder).addFile(file)
  return file
}

function folderByName(name) {
  return DriveApp.getFoldersByName(name).next()
}

function parentByFile(file) {

  try {
    return file.getParents().next()
  } catch(e) {
    return DriveApp.getRootFolder()
  }
}
