jQuery(load)
function load() {

  console.log('login page always run since might not have ?gp-login after logout')
  upgradeBirthdate()

  if ( ~ window.location.search.indexOf('register'))
    register_page()

  if (window.sessionStorage)
    upgradePharmacy() //not needed on this page but fetch && cache the results for a quicker checkout page

  var firstName = jQuery('#first_name_login, #first_name_register')
  var lastName  = jQuery('#last_name_login, #last_name_register')
  var birthDate = jQuery('#birth_date_login, #birth_date_register')

  var verifyFirstName = jQuery('#verify_first_name_login, #verify_first_name_register')
  var verifyLastName = jQuery('#verify_last_name_login, #verify_last_name_register')
  var verifyBirthDate = jQuery('#verify_birth_date_login, #verify_birth_date_register')

  console.log('keyups', firstName, lastName, birthDate)

  firstName.on("change keyup paste", function () {
    console.log('First Name Key Up', firstName.val())
    verifyFirstName.text(firstName.val());
  })

  lastName.on("change keyup paste", function () {
    console.log('Last Name Key Up', lastName.val())
    verifyLastName.text(lastName.val());
  })

  birthDate.on("change keyup paste", function () {
    console.log('Birth Date Key Up', birthDate.val())
    verifyBirthDate.text(birthDate.val());
  })
}

function register_page() {
  console.log('register page')
  //Can't do this in PHP because button text is also "Register" and html inside buttons is escaped as text
  jQuery('#customer_login h2').html('<div class="english">Get Started (Step 1 of 2)</div><div class="spanish">Registro (Paso 1 de 2)</div>')
  jQuery('#customer_login > div').toggle() //hide login column show registration

  clearEmail() //just in case a registration reloads page with the default email populated
  translate()
}
