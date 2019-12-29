function testPost() {
  var event = {parameter:{GD_KEY:GD_KEY}, postData:{contents:{method:'findValue', file:'Order Summary #20660', needle:'(Fee:|Amount Due:)'}}}
  debugEmail(event, doPost(event))
}

function doPost(e) {

  try{

    if (e.parameter.GD_KEY != GD_KEY)
      return debugEmail('gdoc_merge post wrong password', e)

    if ( ! e.postData || ! e.postData.contents)
      return debugEmail('gdoc_merge post not post data', e)

    var response
    var contents = JSON.parse(e.postData.contents)

    if (contents.method == 'mergeDoc')
      response = mergeDoc(contents)

    else
      debugEmail('gdoc_merge post no matching method', e)

    return ContentService
      .createTextOutput(JSON.stringify(response))
      .setMimeType(ContentService.MimeType.JSON)

  } catch(err){
      debugEmail('gdoc_merge post error thrown', err, e)
  }
}
