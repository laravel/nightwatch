import http from 'k6/http'
import { check } from 'k6'

export const options = {
    vus: 1,
    duration: '30s',
}

export default function() {
    check(http.get('http://nightwatch-customer.test/hammer'), {
        'response code was 200': (res) => res.status === 200,
        'response content was "ok"': (res) => res.body.toString() === 'ok',
    })
}
