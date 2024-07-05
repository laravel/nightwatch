import http from 'k6/http'
import { check } from 'k6'

export const options = {
    vus: 20,
    duration: '3m15s',
    cloud: {
        projectID: 3702864,
        name: "Hammer"
    },
}

export default function() {
    check(http.get('http://nightwatch-customer.macdonald.au/hammer'), {
        'response code was 200': (res) => res.status === 200,
        'response content was "ok"': (res) => res.body.toString() === 'ok',
    })
}
