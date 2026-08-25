
import regionsData from '../../json/regions.json'
import provincesData from '../../json/provinces.json'
import municipalitiesData from '../../json/municipalities.json'
import barangaysData from '../../json/barangays.json'
import countries from '../../json/countries.json'

export const country = {
  all: () => {
    return countries.sort(function (a,b) {
      return a.name.localeCompare(b.name)
    })
  }
}


export const region = {
  all: () => {
    return regionsData
  },

  getProvinces: (code) => {
    const filteredProvinces = provincesData.filter(province => province.region_code === code)

    return filteredProvinces.sort(function (a,b) {
      return a.name.localeCompare(b.name)
    })
  },
}

export const province = {

  all: () => {
    return provincesData.sort(function (a,b) {
      return a.name.localeCompare(b.name)
    })
  },

  getMunicipalities: (code) => {

    const filteredMunicipalities = municipalitiesData.filter(municipality => municipality.province_code === code)

    return filteredMunicipalities.sort(function(a,b) {
      return a.name.localeCompare(b.name)
    })
  }
}

export const municipality = {
  all: () => {

    return municipalitiesData.sort(function (a,b) {
      a.name.localeCompare(b.name)
    })
  },

  getBarangays: (code) => {

    const filteredBarangays = barangaysData.filter(barangay => barangay.municipality_code === code)

    return filteredBarangays.sort(function (a,b) {

      return a.name.localeCompare(b.name)
    })
  }
}
