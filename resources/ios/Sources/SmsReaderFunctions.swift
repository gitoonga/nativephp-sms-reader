import Foundation

// iOS does not expose any API for reading SMS messages.
// This stub exists so the plugin compiles for iOS targets.
// The PHP SmsReader::getMessages() returns [] when nativephp_call() is unavailable.

enum SmsReaderFunctions {
    class GetMessages: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            return BridgeResponse.error(
                message: "SMS access is not available on iOS."
            )
        }
    }
}
