#!/bin/sh

set -eu

base_url="${DAVIS_BASE_URL:-http://127.0.0.1:8000}"
base_url="${base_url%/}"
credentials="${DAVIS_TEST_CREDENTIALS:-test_user:password}"
script_dir="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
fixtures="$script_dir/Fixtures/Dav"
temporary_directory="$(mktemp -d "${TMPDIR:-/tmp}/davis-smoke.XXXXXX")"
response="$temporary_directory/response"

cleanup() {
    rm -rf -- "$temporary_directory"
}
trap cleanup EXIT HUP INT TERM

request() {
    expected_status="$1"
    description="$2"
    output="$3"
    shift 3

    actual_status="$(curl --silent --show-error --output "$output" --write-out '%{http_code}' "$@")"
    if [ "$actual_status" != "$expected_status" ]; then
        printf '%s: expected HTTP %s, got %s\n' "$description" "$expected_status" "$actual_status" >&2
        sed -n '1,80p' "$output" >&2
        exit 1
    fi
}

request 401 'unauthenticated DAV request' "$response" "$base_url/dav/"
request 207 'authenticated DAV discovery' "$response" \
    --user "$credentials" --request PROPFIND --header 'Depth: 1' "$base_url/dav/"

calendar_url="$base_url/dav/calendars/test_user/default/davis-smoke-event.ics"
request 201 'CalDAV create' "$response" \
    --user "$credentials" --request PUT --header 'Content-Type: text/calendar; charset=utf-8' \
    --data-binary "@$fixtures/event.ics" "$calendar_url"
request 200 'CalDAV fetch' "$temporary_directory/event.ics" \
    --user "$credentials" "$calendar_url"
cmp "$fixtures/event.ics" "$temporary_directory/event.ics"
request 207 'CalDAV query' "$response" \
    --user "$credentials" --request REPORT --header 'Depth: 1' \
    --header 'Content-Type: application/xml; charset=utf-8' \
    --data-binary "@$fixtures/calendar-query.xml" "$base_url/dav/calendars/test_user/default/"
grep -q 'UID:davis-smoke-event' "$response"
request 204 'CalDAV delete' "$response" \
    --user "$credentials" --request DELETE "$calendar_url"
request 404 'CalDAV deleted resource' "$response" \
    --user "$credentials" "$calendar_url"

contact_url="$base_url/dav/addressbooks/test_user/default/davis-smoke-contact.vcf"
request 201 'CardDAV create' "$response" \
    --user "$credentials" --request PUT --header 'Content-Type: text/vcard; charset=utf-8' \
    --data-binary "@$fixtures/contact.vcf" "$contact_url"
request 200 'CardDAV fetch' "$temporary_directory/contact.vcf" \
    --user "$credentials" "$contact_url"
cmp "$fixtures/contact.vcf" "$temporary_directory/contact.vcf"
request 207 'CardDAV query' "$response" \
    --user "$credentials" --request REPORT --header 'Depth: 1' \
    --header 'Content-Type: application/xml; charset=utf-8' \
    --data-binary "@$fixtures/addressbook-query.xml" "$base_url/dav/addressbooks/test_user/default/"
grep -q 'UID:davis-smoke-contact' "$response"
request 204 'CardDAV delete' "$response" \
    --user "$credentials" --request DELETE "$contact_url"
request 404 'CardDAV deleted resource' "$response" \
    --user "$credentials" "$contact_url"
