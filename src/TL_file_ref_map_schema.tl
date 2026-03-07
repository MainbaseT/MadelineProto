boolFalse#bc799737 = Bool;
boolTrue#997275b5 = Bool;
true#3fedd339 = True;

vector#1cb5c415 {t:Type} # [ t ] = Vector t;

// Root
fileReferenceMap#a71aa6f0 layer:int db_schema:string db_schema_json:string locations:Vector<Location> traversers_incoming:Vector<TraverserIncoming> traversers_outgoing:Vector<TraverserOutgoing> actions:Vector<Action> skipped_sources:Vector<SkippedSource> = FileReferenceMap;

// Locations
locationIncoming#e8a166a4 type:string constructor:string stored_constructor:string = Location;
locationOutgoing#ccb239d6 type:string constructor:string stored_constructor:string = Location;

// Traversers (incoming)
traverseParam#133c1e85 flags:# type:string name:string is_flag:flags.0?true is_vector:flags.1?true = TraverseParam;

traverseIncomingConstructor#ffd2e3b7 type:string constructor:string params:Vector<TraverseParam> push_sources:Vector<Source> is_needed_parent:Bool = TraverserIncoming;
traverseMethodResult#fda9f236 name:string push_sources:Vector<Source> is_needed_parent:Bool = TraverserIncoming;

source#0c779a18 flags:# constructor:string is_constructor:flags.0?true stored_constructor:string stored_params:Vector<FieldExtractor> skipped_flags:Vector<string> needs_parent:flags.3?string parent_is_constructor:flags.4?true = Source;

// Traversers (outgoing)
traverseOutgoingConstructor#d8743ec6 type:string constructor:string params:Vector<TraverseParam> = TraverserOutgoing;
traverseMethodCall#9244f5bb name:string params:Vector<TraverseParam> = TraverserOutgoing;

// Skipped sources
skippedSource#47a7a386 flags:# constructor:string is_constructor:flags.0?true why:string = SkippedSource;

// Refresh actions
action#9539f410 stored_constructor:string action:ActionOp = Action;

// Field extraction path
paramNotFlag#acd9d5cf = ParamFlag;
paramIsFlagAbortIfEmpty#f8fe9fee = ParamFlag;
paramIsFlagFallback#202b77a1 fallback:TypedOp = ParamFlag;
paramIsFlagPassthrough#1dc6e17d = ParamFlag;

pathPart#5a9e1dc4 type:string constructor:string param:string flag:ParamFlag = PathPart;

path#0c3586a2 parts:Vector<PathPart> = Path;
pathParent#58f13684 parts:Vector<PathPart> = Path;

// Extractor
extractAndStore#72069549 from:Path to:string = FieldExtractor;

extractInputStickerSetFromDocumentAttributesAndStore#369d8d14 from:Path to:string = FieldExtractor;
extractInputStickerSetFromStickerSetAndStore#c167d470 from:Path to:string = FieldExtractor;

extractPeerIdFromPeerAndStore#7d33019c from:Path to:string = FieldExtractor;
extractPeerIdFromInputPeerAndStore#a51acfb4 from:Path to:string = FieldExtractor;

extractChannelIdFromChannelAndStore#5675bc97 from:Path to:string = FieldExtractor;
extractChannelIdFromInputChannelAndStore#b662660e from:Path to:string = FieldExtractor;

extractUserIdFromUserAndStore#4778ec63 from:Path to:string = FieldExtractor;
extractUserIdFromInputUserAndStore#7720aa2e from:Path to:string = FieldExtractor;

// Actions
callOp#c2ff3383 method:string args:Vector<TypedOpArg> = ActionOp;
getMessageOp#237849e8 peer:TypedOp id:TypedOp from_scheduled:TypedOp quick_reply_shortcut_id:TypedOp = ActionOp;

// For string => TypedOp dictionaries
typedOpArg#3a2930c2 key:string value:TypedOp = TypedOpArg;

// Typed constructors, the type is specified to simplify codegen,
// but isn't strictly necessary as it can be inferred from the TypedOpOp.
// It is fully pre-validated during the generation of the definition file.
typedOp#705b10ec type:string op:TypedOpOp = TypedOp;

// The actual ops
copyOp#f48f418f from:string = TypedOpOp;

getInputChannelByIdOp#3cb47531 from:string = TypedOpOp;
getInputUserByIdOp#c0ee4326 from:string = TypedOpOp;
getInputPeerByIdOp#19813750 from:string = TypedOpOp;

// Literals & constructors (methods not allowed or needed here)
constructorOp#107f8d8a constructor:string args:Vector<TypedOpArg> = TypedOpOp;

vectorOp#f8fb8f72 values:Vector<TypedOp> = TypedOpOp;

intLiteralOp#cbfabe7c value:int = TypedOpOp;
longLiteralOp#d08b8d3a value:long = TypedOpOp;
stringLiteralOp#2b56ea8e value:string = TypedOpOp;
bytesLiteralOp#fdb395a4 value:bytes = TypedOpOp;
boolLiteralOp#37e07911 value:Bool = TypedOpOp;
doubleLiteralOp#3651e3bf value:double = TypedOpOp;
themeFormatLiteralOp#8e4f9208 = TypedOpOp;
