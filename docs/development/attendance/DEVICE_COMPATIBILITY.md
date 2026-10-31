# Device Compatibility

| Vendor / model | Method | Implemented | Verified on hardware | Notes |
|---|---|---|---|---|
| Hikvision DS-K1T321 | ISAPI pull | Yes | **No** (customer previously tested a sample integration; firmware not recorded) | Request shapes from the tested sample; see `HIKVISION_DS_K1T321.md` |
| Hikvision DS-K1T321 | ISAPI push (HTTP listening) | Yes | **No** | Payload shape from the sample webhook |
| Other Hikvision access terminals (ISAPI) | pull / push | Same adapter | **No** | Page size (10–30) and minor codes vary by model/firmware |
| ZKTeco (PUSH/ADMS firmware) | PUSH | Yes (ATTLOG) | **No** | See `ZKTECO_INTEGRATION.md` |
| ZKTeco (TCP 4370 / Windows SDK only) | — | **No** | — | Protocol not publicly documented |
| Any device with CSV export | Import | Yes | n/a | Register as vendor *Other / manual* |
| Simulator | Simulated pull | Yes | n/a | Events labelled SIMULATED |

Capabilities per device are recorded on the device (`capabilities`) and shown on its page; an
unsupported operation is reported as unsupported, never faked. Compatibility may only be claimed
after the hardware checklist in the vendor document is completed on the real device and firmware.
